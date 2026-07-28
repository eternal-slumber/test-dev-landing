<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\ContactStep;
use App\Models\OutboxMessage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Signature('contact:retry-failed {contactId}')]
#[Description('Retry failed background steps for a contact')]
final class RetryFailedContact extends Command
{
    public function handle(): int
    {
        $contactId = (int) $this->argument('contactId');

        if (! Contact::query()->whereKey($contactId)->exists()) {
            $this->error("Contact [{$contactId}] not found.");

            return self::FAILURE;
        }

        $retried = DB::transaction(function () use ($contactId): int {
            $steps = ContactStep::query()
                ->where('contact_id', $contactId)
                ->where('status', ContactStep::STATUS_FAILED)
                ->lockForUpdate()
                ->get();

            foreach ($steps as $step) {
                $step->update([
                    'status' => ContactStep::STATUS_PENDING,
                    'attempts' => 0,
                    'last_error' => null,
                    'locked_by' => null,
                    'locked_until' => null,
                    'started_at' => null,
                    'finished_at' => null,
                ]);

                OutboxMessage::query()->updateOrCreate(
                    [
                        'contact_id' => $contactId,
                        'type' => $this->outboxType($step->type),
                    ],
                    [
                        'payload' => ['contact_id' => $contactId],
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

            return $steps->count();
        }, attempts: 3);

        $this->info("Retried {$retried} failed step(s) for contact [{$contactId}].");

        return self::SUCCESS;
    }

    private function outboxType(string $stepType): string
    {
        return match ($stepType) {
            ContactStep::TYPE_CLASSIFICATION => OutboxMessage::TYPE_PROCESS_CONTACT,
            ContactStep::TYPE_OWNER_EMAIL => OutboxMessage::TYPE_OWNER_EMAIL,
            ContactStep::TYPE_CONFIRMATION_EMAIL => OutboxMessage::TYPE_CONFIRMATION_EMAIL,
            default => throw new LogicException(
                "Unsupported contact step type [{$stepType}].",
            ),
        };
    }
}
