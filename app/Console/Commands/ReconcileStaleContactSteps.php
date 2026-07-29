<?php

namespace App\Console\Commands;

use App\Models\ContactStep;
use App\Models\OutboxMessage;
use App\Services\ContactStepManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Signature('contacts:reconcile-stale')]
#[Description('Recover contact steps abandoned after their retry window')]
final class ReconcileStaleContactSteps extends Command
{
    public function handle(ContactStepManager $steps): int
    {
        $stepIds = ContactStep::query()
            ->where('status', ContactStep::STATUS_PROCESSING)
            ->whereNotNull('locked_until')
            ->where('locked_until', '<=', now())
            ->orderBy('id')
            ->pluck('id');

        $reconciled = 0;

        foreach ($stepIds as $stepId) {
            if ($this->reconcile((int) $stepId, $steps)) {
                $reconciled++;
            }
        }

        $this->info("Reconciled {$reconciled} stale contact step(s).");

        return self::SUCCESS;
    }

    private function reconcile(int $stepId, ContactStepManager $steps): bool
    {
        return DB::transaction(function () use ($stepId, $steps): bool {
            $step = ContactStep::query()->lockForUpdate()->find($stepId);

            if (
                $step === null
                || $step->status !== ContactStep::STATUS_PROCESSING
                || $step->locked_until === null
                || $step->locked_until->isFuture()
            ) {
                return false;
            }

            $retryDelay = $steps->retryDelayAfterAttempt($step->attempts);

            if (
                $retryDelay !== null
                && $step->locked_until->copy()->addSeconds($retryDelay)->isFuture()
            ) {
                return false;
            }

            if ($retryDelay === null) {
                $this->finishExhaustedStep($step);

                return true;
            }

            $step->update([
                'status' => ContactStep::STATUS_PENDING,
                'attempts' => 0,
                'last_error' => null,
                'locked_by' => null,
                'locked_until' => null,
                'finished_at' => null,
            ]);

            $this->reopenOutbox($step);

            return true;
        }, attempts: 3);
    }

    private function finishExhaustedStep(ContactStep $step): void
    {
        $status = $step->type === ContactStep::TYPE_CLASSIFICATION
            ? ContactStep::STATUS_FALLBACK
            : ContactStep::STATUS_FAILED;

        $step->update([
            'status' => $status,
            'last_error' => 'Processing lease expired after final attempt.',
            'locked_by' => null,
            'locked_until' => null,
            'finished_at' => now(),
        ]);

        if ($step->type !== ContactStep::TYPE_CLASSIFICATION) {
            return;
        }

        foreach ([
            OutboxMessage::TYPE_OWNER_EMAIL,
            OutboxMessage::TYPE_CONFIRMATION_EMAIL,
        ] as $type) {
            OutboxMessage::query()->firstOrCreate(
                [
                    'contact_id' => $step->contact_id,
                    'type' => $type,
                ],
                [
                    'payload' => ['contact_id' => $step->contact_id],
                ],
            );
        }
    }

    private function reopenOutbox(ContactStep $step): void
    {
        $type = match ($step->type) {
            ContactStep::TYPE_CLASSIFICATION => OutboxMessage::TYPE_PROCESS_CONTACT,
            ContactStep::TYPE_OWNER_EMAIL => OutboxMessage::TYPE_OWNER_EMAIL,
            ContactStep::TYPE_CONFIRMATION_EMAIL => OutboxMessage::TYPE_CONFIRMATION_EMAIL,
            default => throw new LogicException(
                "Unsupported contact step type [{$step->type}].",
            ),
        };

        OutboxMessage::query()->updateOrCreate(
            [
                'contact_id' => $step->contact_id,
                'type' => $type,
            ],
            [
                'payload' => ['contact_id' => $step->contact_id],
                'attempts' => 0,
                'available_at' => now(),
                'locked_at' => null,
                'locked_by' => null,
                'published_at' => null,
                'failed_at' => null,
                'last_error' => null,
            ],
        );
    }
}
