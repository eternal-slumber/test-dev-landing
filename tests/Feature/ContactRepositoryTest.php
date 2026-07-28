<?php

use App\DTO\ContactAnalysisResult;
use App\Repositories\ContactRepository;
use App\Repositories\EloquentContactRepository;
use Illuminate\Support\Str;

test('container resolves the contact repository and it creates a contact', function () {
    $repository = app(ContactRepository::class);

    expect($repository)->toBeInstanceOf(EloquentContactRepository::class);

    $contact = $repository->create([
        'name' => 'Иван Петров',
        'phone' => '+7 (999) 123-45-67',
        'email' => 'ivan@example.com',
        'comment' => 'Нужна консультация по проекту.',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $this->assertModelExists($contact);
});

test('repository saves the complete AI analysis', function () {
    $repository = app(ContactRepository::class);
    $contact = $repository->create([
        'name' => 'Иван Петров',
        'phone' => '+7 (999) 123-45-67',
        'email' => 'ivan@example.com',
        'comment' => 'Нужна консультация по проекту.',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $repository->updateAnalysis($contact, new ContactAnalysisResult(
        category: 'consultation',
        summary: 'Запрос на консультацию по проекту.',
        suggestedReply: 'Спасибо за обращение! Готов обсудить задачу.',
    ));

    expect($contact->refresh())
        ->ai_category->toBe('consultation')
        ->ai_summary->toBe('Запрос на консультацию по проекту.')
        ->ai_suggested_reply->toBe('Спасибо за обращение! Готов обсудить задачу.');
});
