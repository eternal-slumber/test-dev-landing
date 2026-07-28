<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactStep;
use App\Models\OutboxMessage;
use App\Repositories\ContactRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ContactService
{
    public function __construct(
        private readonly ContactRepository $contacts,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Contact
    {
        $contact = DB::transaction(function () use ($data): Contact {
            $contact = $this->contacts->create($data);

            if (! $contact->wasRecentlyCreated) {
                $this->ensureIdempotentPayloadMatches($contact, $data);

                return $contact;
            }

            $contact->steps()->createMany([
                ['type' => ContactStep::TYPE_CLASSIFICATION],
                ['type' => ContactStep::TYPE_OWNER_EMAIL],
                ['type' => ContactStep::TYPE_CONFIRMATION_EMAIL],
            ]);

            $contact->outboxMessages()->create([
                'type' => OutboxMessage::TYPE_PROCESS_CONTACT,
                'payload' => ['contact_id' => $contact->id],
            ]);

            return $contact;
        }, attempts: 3);

        Context::add('contact_id', $contact->id);

        return $contact;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ensureIdempotentPayloadMatches(Contact $contact, array $data): void
    {
        $fields = ['name', 'phone', 'email', 'comment'];

        if ($contact->only($fields) !== Arr::only($data, $fields)) {
            throw new ConflictHttpException(
                'Idempotency-Key уже использован для другого обращения.',
            );
        }
    }
}
