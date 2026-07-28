<?php

use App\Mail\ContactReceivedConfirmation;
use App\Mail\ContactReceivedForOwner;
use App\Models\Contact;

test('owner email contains AI analysis, contact details and reply link', function () {
    $contact = Contact::factory()->create([
        'name' => 'Артем',
        'phone' => '+7 999 123-45-67',
        'email' => 'artem@example.com',
        'comment' => 'Хочу обсудить разработку веб-сервиса.',
        'ai_category' => 'project',
        'ai_summary' => 'Запрос на разработку веб-сервиса.',
        'ai_suggested_reply' => 'Спасибо за обращение! Готов обсудить требования.',
    ]);
    $mailto = 'mailto:'.$contact->email.'?'.http_build_query([
        'subject' => 'Re: Ваше обращение',
        'body' => $contact->ai_suggested_reply,
    ], '', '&', PHP_QUERY_RFC3986);

    (new ContactReceivedForOwner($contact))
        ->assertHasSubject("Новое обращение #{$contact->id}")
        ->assertSeeInOrderInHtml([
            'Проект',
            $contact->ai_summary,
            $contact->name,
            $contact->email,
            $contact->phone,
            $contact->ai_suggested_reply,
            'Ответить отправителю',
            $contact->comment,
        ])
        ->assertSeeInHtml($mailto);
});

test('owner email shows when classification is unavailable', function () {
    $contact = Contact::factory()->create([
        'ai_category' => null,
        'ai_summary' => null,
        'ai_suggested_reply' => null,
    ]);

    (new ContactReceivedForOwner($contact))
        ->assertSeeInHtml('AI-анализ временно недоступен.')
        ->assertSeeInHtml($contact->comment)
        ->assertDontSeeInHtml('Ответить отправителю');
});

test('confirmation email contains the user comment without internal details', function () {
    $contact = Contact::factory()->create([
        'name' => 'Артем',
        'comment' => 'Хочу обсудить разработку веб-сервиса.',
        'ai_category' => 'project',
        'ai_summary' => 'Запрос на разработку веб-сервиса.',
        'ai_suggested_reply' => 'Спасибо за обращение! Готов обсудить требования.',
    ]);

    (new ContactReceivedConfirmation($contact))
        ->assertHasSubject('Ваше обращение получено')
        ->assertSeeInHtml($contact->name)
        ->assertSeeInHtml($contact->comment)
        ->assertDontSeeInHtml($contact->ai_category)
        ->assertDontSeeInHtml($contact->ai_summary)
        ->assertDontSeeInHtml($contact->ai_suggested_reply)
        ->assertDontSeeInHtml('AI-анализ временно недоступен.');
});
