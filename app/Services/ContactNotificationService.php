<?php

namespace App\Services;

use App\Mail\ContactReceivedConfirmation;
use App\Mail\ContactReceivedForOwner;
use App\Models\Contact;
use Illuminate\Support\Facades\Mail;

final class ContactNotificationService
{
    public function send(Contact $contact): void
    {
        Mail::to((string) config('mail.contact_owner'))->send(new ContactReceivedForOwner($contact));

        Mail::to($contact->email, $contact->name)->send(new ContactReceivedConfirmation($contact));
    }
}
