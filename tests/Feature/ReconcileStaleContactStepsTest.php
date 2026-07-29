<?php

use App\Jobs\ProcessContact;
use App\Models\Contact;
use App\Models\ContactStep;
use App\Models\OutboxMessage;
use App\Repositories\ContactRepository;
use App\Services\ContactStepManager;
use App\Services\OpenAiContactClassifier;
use App\Services\OutboxPublisher;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

test('lost retryable step is reopened in outbox automatically', function () {
    $contact = Contact::factory()->create();
    $staleStep = $contact->steps()->create([
        'type' => ContactStep::TYPE_CLASSIFICATION,
        'status' => ContactStep::STATUS_PROCESSING,
        'attempts' => 2,
        'locked_by' => (string) Str::uuid(),
        'locked_until' => now()->subMinutes(3),
        'started_at' => now()->subMinutes(5),
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
        ->status->toBe(ContactStep::STATUS_PENDING)
        ->attempts->toBe(0)
        ->last_error->toBeNull()
        ->locked_by->toBeNull()
        ->locked_until->toBeNull()
        ->finished_at->toBeNull()
        ->and($activeStep->refresh()->status)
        ->toBe(ContactStep::STATUS_PROCESSING)
        ->and($message->refresh())
        ->published_at->toBeNull()
        ->failed_at->toBeNull()
        ->and($message->available_at->isPast())->toBeTrue();

    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('dispatch')->once();

    expect((new OutboxPublisher($dispatcher))->publishBatch())->toBe(1)
        ->and($message->refresh()->published_at)->not->toBeNull();
});

test('recovered job gets a fresh retry cycle', function () {
    config([
        'services.openai.key' => 'test-key',
        'services.openai.model' => 'test-model',
        'services.openai.url' => 'https://api.openai.test/v1/chat/completions',
        'services.openai.connect_timeout' => 1,
        'services.openai.timeout' => 1,
    ]);
    Http::fake([
        'https://api.openai.test/v1/chat/completions' => Http::response([], 500),
    ]);

    $contact = Contact::factory()->create([
        'comment' => 'Хочу обсудить разработку веб-сервиса.',
    ]);
    $step = $contact->steps()->create([
        'type' => ContactStep::TYPE_CLASSIFICATION,
        'status' => ContactStep::STATUS_PROCESSING,
        'attempts' => 2,
        'locked_by' => (string) Str::uuid(),
        'locked_until' => now()->subMinutes(3),
        'started_at' => now()->subMinutes(5),
    ]);
    $contact->outboxMessages()->create([
        'type' => OutboxMessage::TYPE_PROCESS_CONTACT,
        'payload' => ['contact_id' => $contact->id],
        'published_at' => now(),
    ]);

    $this->artisan('contacts:reconcile-stale')->assertSuccessful();

    $recoveredJob = null;
    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::on(function (object $job) use (&$recoveredJob): bool {
            if (! $job instanceof ProcessContact) {
                return false;
            }

            $recoveredJob = $job;

            return true;
        }));

    expect((new OutboxPublisher($dispatcher))->publishBatch())->toBe(1)
        ->and($recoveredJob)->toBeInstanceOf(ProcessContact::class);

    foreach (range(1, 3) as $_attempt) {
        expect(fn () => $recoveredJob->handle(
            app(ContactRepository::class),
            app(ContactStepManager::class),
            app(OpenAiContactClassifier::class),
        ))->toThrow(RequestException::class);
    }

    $step->refresh()->update(['locked_until' => now()->subMinute()]);

    $this->artisan('contacts:reconcile-stale')
        ->expectsOutput('Reconciled 0 stale contact step(s).')
        ->assertSuccessful();

    expect($step->refresh())
        ->status->toBe(ContactStep::STATUS_PROCESSING)
        ->attempts->toBe(3)
        ->locked_by->toBe($recoveredJob->workerId)
        ->and($contact->outboxMessages()->count())->toBe(1);

    Http::assertSentCount(3);
});

test('third attempt waiting for final backoff is not reconciled', function () {
    $contact = Contact::factory()->create();
    $step = $contact->steps()->create([
        'type' => ContactStep::TYPE_CLASSIFICATION,
        'status' => ContactStep::STATUS_PROCESSING,
        'attempts' => 3,
        'locked_by' => (string) Str::uuid(),
        'locked_until' => now()->subMinute(),
        'started_at' => now()->subMinutes(3),
    ]);
    $message = $contact->outboxMessages()->create([
        'type' => OutboxMessage::TYPE_PROCESS_CONTACT,
        'payload' => ['contact_id' => $contact->id],
        'published_at' => now(),
    ]);

    $this->artisan('contacts:reconcile-stale')
        ->expectsOutput('Reconciled 0 stale contact step(s).')
        ->assertSuccessful();

    expect($step->refresh())
        ->status->toBe(ContactStep::STATUS_PROCESSING)
        ->attempts->toBe(3)
        ->locked_by->not->toBeNull()
        ->locked_until->not->toBeNull()
        ->and($message->refresh()->published_at)->not->toBeNull();
});

test('step attempts cannot exceed the queue maximum', function () {
    $contact = Contact::factory()->create();
    $workerId = (string) Str::uuid();
    $step = $contact->steps()->create([
        'type' => ContactStep::TYPE_OWNER_EMAIL,
        'status' => ContactStep::STATUS_PROCESSING,
        'attempts' => ContactStepManager::MAX_ATTEMPTS,
        'locked_by' => $workerId,
        'locked_until' => now()->addMinute(),
        'started_at' => now()->subMinutes(5),
    ]);

    expect(app(ContactStepManager::class)->claim(
        $contact->id,
        ContactStep::TYPE_OWNER_EMAIL,
        $workerId,
    ))->toBeFalse()
        ->and($step->refresh()->attempts)
        ->toBe(ContactStepManager::MAX_ATTEMPTS);
});

test('exhausted stale classification falls back and enqueues emails', function () {
    $contact = Contact::factory()->create();
    $step = $contact->steps()->create([
        'type' => ContactStep::TYPE_CLASSIFICATION,
        'status' => ContactStep::STATUS_PROCESSING,
        'attempts' => 4,
        'locked_by' => (string) Str::uuid(),
        'locked_until' => now()->subMinute(),
        'started_at' => now()->subMinutes(10),
    ]);
    $contact->outboxMessages()->create([
        'type' => OutboxMessage::TYPE_PROCESS_CONTACT,
        'payload' => ['contact_id' => $contact->id],
        'published_at' => now(),
    ]);

    $this->artisan('contacts:reconcile-stale')
        ->expectsOutput('Reconciled 1 stale contact step(s).')
        ->assertSuccessful();

    expect($step->refresh())
        ->status->toBe(ContactStep::STATUS_FALLBACK)
        ->last_error->toBe('Processing lease expired after final attempt.')
        ->locked_by->toBeNull()
        ->locked_until->toBeNull()
        ->finished_at->not->toBeNull()
        ->and($contact->outboxMessages()
            ->whereIn('type', [
                OutboxMessage::TYPE_OWNER_EMAIL,
                OutboxMessage::TYPE_CONFIRMATION_EMAIL,
            ])
            ->count())->toBe(2);
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
