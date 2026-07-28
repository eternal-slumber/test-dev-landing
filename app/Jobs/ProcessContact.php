<?php

namespace App\Jobs;

use App\Models\ContactStep;
use App\Models\OutboxMessage;
use App\Repositories\ContactRepository;
use App\Services\ContactStepManager;
use App\Services\OpenAiContactClassifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

#[Tries(4)]
#[Backoff(10, 60, 300)]
#[Timeout(60)]
final class ProcessContact implements ShouldQueue
{
    use Queueable;

    public readonly string $workerId;

    public function __construct(
        public readonly int $contactId,
        ?string $workerId = null,
    ) {
        $this->workerId = $workerId ?? (string) Str::uuid();
    }

    public function handle(
        ContactRepository $contacts,
        ContactStepManager $steps,
        OpenAiContactClassifier $classifier,
    ): void {
        if (! $steps->claim(
            $this->contactId,
            ContactStep::TYPE_CLASSIFICATION,
            $this->workerId,
        )) {
            return;
        }

        $contact = $contacts->findOrFail($this->contactId);

        $analysis = $classifier->classify($contact->comment);

        DB::transaction(function () use (
            $contacts,
            $steps,
            $contact,
            $analysis,
        ): void {
            if ($analysis->isAvailable()) {
                $contacts->updateAnalysis($contact, $analysis);
            }

            $steps->complete(
                $contact->id,
                ContactStep::TYPE_CLASSIFICATION,
                $this->workerId,
                $analysis->isAvailable()
                    ? ContactStep::STATUS_SUCCEEDED
                    : ContactStep::STATUS_FALLBACK,
            );

            $this->createEmailMessages($contact->id);
        });
    }

    public function failed(?Throwable $exception): void
    {
        $error = $exception === null ? 'UnknownError' : $exception::class;

        try {
            DB::transaction(function () use ($error): void {
                ContactStep::query()
                    ->where('contact_id', $this->contactId)
                    ->where('type', ContactStep::TYPE_CLASSIFICATION)
                    ->where('locked_by', $this->workerId)
                    ->update([
                        'status' => ContactStep::STATUS_FALLBACK,
                        'last_error' => $error,
                        'locked_by' => null,
                        'locked_until' => null,
                        'finished_at' => now(),
                    ]);

                $this->createEmailMessages($this->contactId);
            });
        } catch (Throwable $fallbackException) {
            report($fallbackException);
        }

        Log::error('Contact processing permanently failed.', [
            'contact_id' => $this->contactId,
            'exception' => $error,
        ]);
    }

    private function createEmailMessages(int $contactId): void
    {
        foreach ([
            OutboxMessage::TYPE_OWNER_EMAIL,
            OutboxMessage::TYPE_CONFIRMATION_EMAIL,
        ] as $type) {
            OutboxMessage::query()->firstOrCreate(
                [
                    'contact_id' => $contactId,
                    'type' => $type,
                ],
                [
                    'payload' => ['contact_id' => $contactId],
                ],
            );
        }
    }
}
