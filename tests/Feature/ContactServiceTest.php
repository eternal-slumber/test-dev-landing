<?php

use App\Models\ContactStep;
use App\Models\OutboxMessage;
use App\Services\ContactService;
use Illuminate\Support\Str;

test('container resolves the contact service and it creates a contact', function () {
    $service = app(ContactService::class);

    expect($service)->toBeInstanceOf(ContactService::class);

    $contact = $service->create([
        'name' => 'Иван Петров',
        'phone' => '+7 (999) 123-45-67',
        'email' => 'ivan@example.com',
        'comment' => 'Нужна консультация по проекту.',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $this->assertModelExists($contact);

    expect($contact->steps()->count())->toBe(3)
        ->and($contact->steps()->pluck('status')->unique()->all())
        ->toBe([ContactStep::STATUS_PENDING])
        ->and($contact->outboxMessages()->sole()->type)
        ->toBe(OutboxMessage::TYPE_PROCESS_CONTACT);
});
