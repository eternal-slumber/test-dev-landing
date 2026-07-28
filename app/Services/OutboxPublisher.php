<?php

namespace App\Services;

use App\Jobs\ProcessContact;
use App\Jobs\SendContactConfirmationEmail;
use App\Jobs\SendContactOwnerEmail;
use App\Models\ContactStep;
use App\Models\OutboxMessage;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

final class OutboxPublisher
{
    private const int MAX_ATTEMPTS = 10;

    private readonly string $workerId;

    public function __construct(
        private readonly Dispatcher $bus,
    ) {
        $this->workerId = (string) Str::uuid();
    }

    public function publishBatch(int $limit = 50): int
    {
        $messages = $this->claim($limit);

        foreach ($messages as $message) {
            $this->publish($message);
        }

        return $messages->count();
    }

    /** @return Collection<int, OutboxMessage> */
    private function claim(int $limit): Collection
    {
        return DB::transaction(function () use ($limit): Collection {
            $query = OutboxMessage::query()
                ->whereNull('published_at')
                ->whereNull('failed_at')
                ->where('available_at', '<=', now())
                ->where(function ($query): void {
                    $query
                        ->whereNull('locked_at')
                        ->orWhere('locked_at', '<=', now()->subMinutes(5));
                })
                ->orderBy('id')
                ->limit(max(1, $limit));

            if (DB::connection()->getDriverName() === 'pgsql') {
                $query->lock('for update skip locked');
            } else {
                $query->lockForUpdate();
            }

            $messages = $query->get();

            foreach ($messages as $message) {
                $message->update([
                    'locked_at' => now(),
                    'locked_by' => $this->workerId,
                ]);
            }

            return $messages;
        });
    }

    private function publish(OutboxMessage $message): void
    {
        try {
            $job = match ($message->type) {
                OutboxMessage::TYPE_PROCESS_CONTACT => (new ProcessContact(
                    $message->contact_id,
                ))->onConnection('redis')->onQueue('processing'),
                OutboxMessage::TYPE_OWNER_EMAIL => (new SendContactOwnerEmail(
                    $message->contact_id,
                ))->onConnection('redis')->onQueue('mail'),
                OutboxMessage::TYPE_CONFIRMATION_EMAIL => (new SendContactConfirmationEmail(
                    $message->contact_id,
                ))->onConnection('redis')->onQueue('mail'),
                default => throw new LogicException(
                    "Unsupported outbox message type [{$message->type}].",
                ),
            };

            $this->bus->dispatch($job);

            OutboxMessage::query()
                ->whereKey($message->id)
                ->where('locked_by', $this->workerId)
                ->update([
                    'published_at' => now(),
                    'locked_at' => null,
                    'locked_by' => null,
                    'last_error' => null,
                ]);
        } catch (Throwable $exception) {
            $this->releaseAfterFailure($message, $exception);
        }
    }

    private function releaseAfterFailure(
        OutboxMessage $message,
        Throwable $exception,
    ): void {
        $attempts = $message->attempts + 1;
        $delay = match ($message->attempts) {
            0 => 10,
            1 => 60,
            default => 300,
        };

        $updates = [
            'attempts' => $attempts,
            'locked_at' => null,
            'locked_by' => null,
            'last_error' => $exception::class,
        ];

        if ($attempts >= self::MAX_ATTEMPTS) {
            $updates['failed_at'] = now();
        } else {
            $updates['available_at'] = now()->addSeconds($delay);
        }

        $updated = DB::transaction(function () use (
            $message,
            $exception,
            $attempts,
            $updates,
        ): int {
            $updated = OutboxMessage::query()
                ->whereKey($message->id)
                ->where('locked_by', $this->workerId)
                ->update($updates);

            if ($updated === 0 || $attempts < self::MAX_ATTEMPTS) {
                return $updated;
            }

            $stepType = $this->contactStepType($message->type);

            if ($stepType !== null) {
                ContactStep::query()
                    ->where('contact_id', $message->contact_id)
                    ->where('type', $stepType)
                    ->where('status', ContactStep::STATUS_PENDING)
                    ->update([
                        'status' => ContactStep::STATUS_FAILED,
                        'last_error' => $exception::class,
                        'locked_by' => null,
                        'locked_until' => null,
                        'finished_at' => now(),
                    ]);
            }

            return $updated;
        });

        if ($updated === 0) {
            return;
        }

        $context = [
            'outbox_message_id' => $message->id,
            'contact_id' => $message->contact_id,
            'type' => $message->type,
            'attempts' => $attempts,
            'exception' => $exception::class,
        ];

        if ($attempts >= self::MAX_ATTEMPTS) {
            Log::critical('Outbox message permanently failed.', $context);

            return;
        }

        Log::warning('Outbox message publish failed.', $context);
    }

    private function contactStepType(string $outboxType): ?string
    {
        return match ($outboxType) {
            OutboxMessage::TYPE_PROCESS_CONTACT => ContactStep::TYPE_CLASSIFICATION,
            OutboxMessage::TYPE_OWNER_EMAIL => ContactStep::TYPE_OWNER_EMAIL,
            OutboxMessage::TYPE_CONFIRMATION_EMAIL => ContactStep::TYPE_CONFIRMATION_EMAIL,
            default => null,
        };
    }
}
