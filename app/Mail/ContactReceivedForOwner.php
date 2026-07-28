<?php

namespace App\Mail;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class ContactReceivedForOwner extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Contact $contact,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Новое обращение #{$this->contact->id}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.contacts.owner',
            with: [
                'categoryLabel' => $this->categoryLabel(),
                'mailto' => $this->mailto(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    private function categoryLabel(): string
    {
        return match ($this->contact->ai_category) {
            'project' => 'Проект',
            'employment' => 'Трудоустройство',
            'consultation' => 'Консультация',
            'other' => 'Другое',
            default => 'Не определена',
        };
    }

    private function mailto(): string
    {
        return 'mailto:'.$this->contact->email.'?'.http_build_query([
            'subject' => 'Re: Ваше обращение',
            'body' => $this->contact->ai_suggested_reply ?? '',
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
