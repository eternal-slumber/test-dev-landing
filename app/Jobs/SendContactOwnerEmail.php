<?php

namespace App\Jobs;

use App\Mail\ContactReceivedForOwner;
use App\Models\ContactStep;
use App\Repositories\ContactRepository;
use App\Services\ContactStepManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

#[Tries(4)]
#[Backoff(10, 60, 300)]
#[Timeout(30)]
final class SendContactOwnerEmail implements ShouldQueue
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
    ): void {
        if (! $steps->claim(
            $this->contactId,
            ContactStep::TYPE_OWNER_EMAIL,
            $this->workerId,
        )) {
            return;
        }

        $contact = $contacts->findOrFail($this->contactId);

        Mail::to((string) config('mail.contact_owner'))
            ->send(new ContactReceivedForOwner($contact));

        $steps->complete(
            $contact->id,
            ContactStep::TYPE_OWNER_EMAIL,
            $this->workerId,
            ContactStep::STATUS_SENT,
        );
    }

    public function failed(?Throwable $exception): void
    {
        $error = $exception === null ? 'UnknownError' : $exception::class;

        ContactStep::query()
            ->where('contact_id', $this->contactId)
            ->where('type', ContactStep::TYPE_OWNER_EMAIL)
            ->where('locked_by', $this->workerId)
            ->update([
                'status' => ContactStep::STATUS_FAILED,
                'last_error' => $error,
                'locked_by' => null,
                'locked_until' => null,
                'finished_at' => now(),
            ]);

        Log::error('Owner contact email permanently failed.', [
            'contact_id' => $this->contactId,
            'exception' => $error,
        ]);
    }
}
