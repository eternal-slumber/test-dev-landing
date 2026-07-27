<x-mail::message>
# Новое обращение

**Имя:** {{ $contact->name }}

**Телефон:** {{ $contact->phone }}

**Email:** {{ $contact->email }}

**AI-категория:** {{ $contact->ai_category ?? 'AI недоступен' }}

## Комментарий

<x-mail::panel>
{{ $contact->comment }}
</x-mail::panel>

{{ config('app.name') }}
</x-mail::message>
