<x-mail::message>
# Мы получили ваше обращение

Здравствуйте, {{ $contact->name }}!

Спасибо за обращение. Мы получили ваше сообщение и свяжемся с вами.

## Ваш комментарий

<x-mail::panel>
{{ $contact->comment }}
</x-mail::panel>
</x-mail::message>
