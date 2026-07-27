<?php

namespace App\Services;

use App\Models\Contact;
use App\Repositories\ContactRepository;

final class ContactService
{
    public function __construct(
        private readonly ContactRepository $contacts,
        private readonly OpenAiContactClassifier $classifier,
        private readonly ContactNotificationService $notifications
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Contact
    {
        $contact = $this->contacts->create($data);
        $classification = $this->classifier->classify($contact->comment);

        if ($classification->category !== null) {
            $this->contacts->updateCategory($contact, $classification->category);
        }

        $this->notifications->send($contact);

        return $contact;
    }
}
