<?php

use App\Models\Contact;
use Illuminate\Support\Str;

test('a contact can be created from validated attributes', function () {
    $contact = Contact::create([
        'name' => 'Иван Петров',
        'phone' => '+7 999 123-45-67',
        'email' => 'ivan@example.com',
        'comment' => 'Нужна разработка нового проекта.',
        'ai_category' => 'project',
        'ai_summary' => 'Запрос на разработку нового проекта.',
        'ai_suggested_reply' => 'Спасибо за обращение! Готов обсудить требования.',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $this->assertModelExists($contact);

    expect($contact)
        ->name->toBe('Иван Петров')
        ->phone->toBe('+7 999 123-45-67')
        ->email->toBe('ivan@example.com')
        ->comment->toBe('Нужна разработка нового проекта.')
        ->ai_category->toBe('project')
        ->ai_summary->toBe('Запрос на разработку нового проекта.')
        ->ai_suggested_reply->toBe('Спасибо за обращение! Готов обсудить требования.')
        ->idempotency_key->not->toBeNull();
});

test('the contact factory creates a valid contact', function () {
    $contact = Contact::factory()->create();

    $this->assertModelExists($contact);

    expect($contact)
        ->name->not->toBeEmpty()
        ->phone->not->toBeEmpty()
        ->email->not->toBeEmpty()
        ->comment->not->toBeEmpty()
        ->ai_category->toBeNull()
        ->ai_summary->toBeNull()
        ->ai_suggested_reply->toBeNull();
});
