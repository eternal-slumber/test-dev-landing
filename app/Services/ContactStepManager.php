<?php

namespace App\Services;

use App\Models\ContactStep;
use Illuminate\Support\Facades\DB;

final class ContactStepManager
{
    public const int MAX_ATTEMPTS = 4;

    public const int FIRST_RETRY_DELAY_SECONDS = 10;

    public const int SECOND_RETRY_DELAY_SECONDS = 60;

    public const int THIRD_RETRY_DELAY_SECONDS = 300;

    private const int LEASE_SECONDS = 120;

    public function claim(
        int $contactId,
        string $type,
        string $workerId,
    ): bool {
        return DB::transaction(function () use ($contactId, $type, $workerId): bool {
            $step = ContactStep::query()
                ->where('contact_id', $contactId)
                ->where('type', $type)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->isTerminal($step)) {
                return false;
            }

            if ($step->attempts >= self::MAX_ATTEMPTS) {
                return false;
            }

            if (
                $step->status === ContactStep::STATUS_PROCESSING
                && $step->locked_by !== $workerId
                && $step->locked_until?->isFuture()
            ) {
                return false;
            }

            $step->update([
                'status' => ContactStep::STATUS_PROCESSING,
                'attempts' => $step->attempts + 1,
                'last_error' => null,
                'locked_by' => $workerId,
                'locked_until' => now()->addSeconds(self::LEASE_SECONDS),
                'started_at' => $step->started_at ?? now(),
                'finished_at' => null,
            ]);

            return true;
        });
    }

    public function retryDelayAfterAttempt(int $attempts): ?int
    {
        return match ($attempts) {
            0, 1 => self::FIRST_RETRY_DELAY_SECONDS,
            2 => self::SECOND_RETRY_DELAY_SECONDS,
            3 => self::THIRD_RETRY_DELAY_SECONDS,
            default => null,
        };
    }

    public function complete(
        int $contactId,
        string $type,
        string $workerId,
        string $status,
    ): void {
        ContactStep::query()
            ->where('contact_id', $contactId)
            ->where('type', $type)
            ->where('locked_by', $workerId)
            ->update([
                'status' => $status,
                'last_error' => null,
                'locked_by' => null,
                'locked_until' => null,
                'finished_at' => now(),
            ]);
    }

    private function isTerminal(ContactStep $step): bool
    {
        return in_array($step->status, [
            ContactStep::STATUS_SUCCEEDED,
            ContactStep::STATUS_FALLBACK,
            ContactStep::STATUS_SENT,
            ContactStep::STATUS_FAILED,
        ], true);
    }
}
