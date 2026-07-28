<?php

namespace App\Console\Commands;

use App\Models\ContactStep;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('contacts:reconcile-stale')]
#[Description('Mark contact steps with expired processing leases as failed')]
final class ReconcileStaleContactSteps extends Command
{
    public function handle(): int
    {
        $reconciled = ContactStep::query()
            ->where('status', ContactStep::STATUS_PROCESSING)
            ->whereNotNull('locked_until')
            ->where('locked_until', '<=', now())
            ->update([
                'status' => ContactStep::STATUS_FAILED,
                'last_error' => 'Processing lease expired.',
                'locked_by' => null,
                'locked_until' => null,
                'finished_at' => now(),
            ]);

        $this->info("Reconciled {$reconciled} stale contact step(s).");

        return self::SUCCESS;
    }
}
