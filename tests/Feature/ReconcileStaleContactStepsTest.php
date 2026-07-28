<?php

use App\Models\Contact;
use App\Models\ContactStep;
use App\Models\OutboxMessage;
use App\Services\OutboxPublisher;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Str;

test('stale processing step can be reconciled and retried', function () {
    $contact = Contact::factory()->create();
    $staleStep = $contact->steps()->create([
        'type' => ContactStep::TYPE_CLASSIFICATION,
        'status' => ContactStep::STATUS_PROCESSING,
        'attempts' => 4,
        'locked_by' => (string) Str::uuid(),
        'locked_until' => now()->subMinute(),
        'started_at' => now()->subMinutes(3),
    ]);
    $activeStep = $contact->steps()->create([
        'type' => ContactStep::TYPE_OWNER_EMAIL,
        'status' => ContactStep::STATUS_PROCESSING,
        'locked_by' => (string) Str::uuid(),
        'locked_until' => now()->addMinute(),
        'started_at' => now(),
    ]);
    $message = $contact->outboxMessages()->create([
        'type' => OutboxMessage::TYPE_PROCESS_CONTACT,
        'payload' => ['contact_id' => $contact->id],
        'published_at' => now(),
    ]);

    $this->artisan('contacts:reconcile-stale')
        ->expectsOutput('Reconciled 1 stale contact step(s).')
        ->assertSuccessful();

    expect($staleStep->refresh())
        ->status->toBe(ContactStep::STATUS_FAILED)
        ->last_error->toBe('Processing lease expired.')
        ->locked_by->toBeNull()
        ->locked_until->toBeNull()
        ->finished_at->not->toBeNull()
        ->and($activeStep->refresh()->status)
        ->toBe(ContactStep::STATUS_PROCESSING);

    $this->artisan('contact:retry-failed', ['contactId' => $contact->id])
        ->expectsOutput("Retried 1 failed step(s) for contact [{$contact->id}].")
        ->assertSuccessful();

    expect($staleStep->refresh())
        ->status->toBe(ContactStep::STATUS_PENDING)
        ->attempts->toBe(0)
        ->last_error->toBeNull()
        ->and($message->refresh())
        ->published_at->toBeNull()
        ->failed_at->toBeNull()
        ->and($message->available_at->isPast())->toBeTrue();

    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('dispatch')->once();

    expect((new OutboxPublisher($dispatcher))->publishBatch())->toBe(1)
        ->and($message->refresh()->published_at)->not->toBeNull();
});

test('stale reconciliation is scheduled every five minutes without overlap', function () {
    $event = collect(app(Schedule::class)->events())->first(
        fn ($event): bool => str_contains(
            $event->command,
            'contacts:reconcile-stale',
        ),
    );

    expect($event?->expression)->toBe('*/5 * * * *')
        ->and($event?->withoutOverlapping)->toBeTrue();
});
