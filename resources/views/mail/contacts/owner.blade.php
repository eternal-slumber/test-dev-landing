<x-mail::message>
# Новое обращение #{{ $contact->id }}

@if ($contact->ai_summary !== null)
**Категория:** {{ $categoryLabel }}

## Краткое содержание

{{ $contact->ai_summary }}
@else
**AI-анализ временно недоступен.**
@endif

## Контакты

**Имя:** {{ $contact->name }}

**Email:** {{ $contact->email }}

**Телефон:** {{ $contact->phone }}

@if ($contact->ai_suggested_reply !== null)
## Предложенный ответ

<x-mail::panel>
{{ $contact->ai_suggested_reply }}
</x-mail::panel>

<x-mail::button :url="$mailto">
Ответить отправителю
</x-mail::button>
@endif

## Полный текст обращения

<x-mail::panel>
{{ $contact->comment }}
</x-mail::panel>
</x-mail::message>
