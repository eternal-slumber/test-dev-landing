<?php

namespace App\Services;

use App\Models\ContactStep;
use Illuminate\Support\Facades\DB;

final class ContactStepManager
{
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
                'locked_until' => now()->addMinutes(2),
                'started_at' => $step->started_at ?? now(),
                'finished_at' => null,
            ]);

            return true;
        });
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
