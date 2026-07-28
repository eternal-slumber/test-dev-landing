<?php

use App\Jobs\ProcessContact;
use App\Jobs\SendContactConfirmationEmail;
use App\Jobs\SendContactOwnerEmail;
use App\Mail\ContactReceivedConfirmation;
use App\Mail\ContactReceivedForOwner;
use App\Models\Contact;
use App\Models\ContactStep;
use App\Models\OutboxMessage;
use App\Repositories\ContactRepository;
use App\Services\ContactService;
use App\Services\ContactStepManager;
use App\Services\OpenAiContactClassifier;
use App\Services\OutboxPublisher;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function createContactForOutbox(): Contact
{
    return app(ContactService::class)->create([
        'name' => 'Артем',
        'phone' => '+7 (999) 123-45-67',
        'email' => 'artem@example.com',
        'comment' => 'Хочу обсудить разработку веб-сервиса.',
        'idempotency_key' => (string) Str::uuid(),
    ]);
}

beforeEach(function () {
    config([
        'mail.contact_owner' => 'owner@example.com',
        'services.openai.key' => 'test-key',
        'services.openai.model' => 'gpt-5-mini',
        'services.openai.url' => 'https://api.openai.test/v1/chat/completions',
        'services.openai.connect_timeout' => 1,
        'services.openai.timeout' => 1,
    ]);

    Http::preventStrayRequests();
    Mail::fake();
    Queue::fake();
});

test('outbox relay publishes processing job and marks message', function () {
    $contact = createContactForOutbox();

    expect(app(OutboxPublisher::class)->publishBatch())->toBe(1);

    Queue::assertPushedOn(
        'processing',
        ProcessContact::class,
        fn (ProcessContact $job): bool => $job->contactId === $contact->id,
    );

    expect($contact->outboxMessages()->sole()->published_at)->not->toBeNull();
});

test('failed queue publish keeps outbox message for retry', function () {
    $contact = createContactForOutbox();
    $dispatcher = Mockery::mock(Dispatcher::class);

    $dispatcher
        ->shouldReceive('dispatch')
        ->once()
        ->andThrow(new RuntimeException('Redis unavailable'));

    expect((new OutboxPublisher($dispatcher))->publishBatch())->toBe(1);

    $message = $contact->outboxMessages()->sole();

    expect($message)
        ->published_at->toBeNull()
        ->failed_at->toBeNull()
        ->locked_at->toBeNull()
        ->locked_by->toBeNull()
        ->attempts->toBe(1)
        ->last_error->toBe(RuntimeException::class)
        ->and($message->available_at->isFuture())->toBeTrue();
});

test('outbox stops retrying and logs critical after ten failures', function () {
    $contact = createContactForOutbox();
    $contact->outboxMessages()->sole()->update(['attempts' => 9]);

    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher
        ->shouldReceive('dispatch')
        ->once()
        ->andThrow(new RuntimeException('Invalid outbox payload'));

    Log::spy();

    $publisher = new OutboxPublisher($dispatcher);

    expect($publisher->publishBatch())->toBe(1)
        ->and($publisher->publishBatch())->toBe(0);

    $message = $contact->outboxMessages()->sole();

    expect($message)
        ->attempts->toBe(10)
        ->failed_at->not->toBeNull()
        ->published_at->toBeNull()
        ->locked_at->toBeNull()
        ->locked_by->toBeNull();

    Log::shouldHaveReceived('critical')
        ->once()
        ->with(
            'Outbox message permanently failed.',
            Mockery::on(fn (array $context): bool => $context['outbox_message_id'] === $message->id
                && $context['attempts'] === 10),
        );
});

test('retry command resets failed step and reopens its outbox message', function () {
    $contact = createContactForOutbox();
    $step = $contact->steps()
        ->where('type', ContactStep::TYPE_OWNER_EMAIL)
        ->sole();

    $step->update([
        'status' => ContactStep::STATUS_FAILED,
        'attempts' => 4,
        'last_error' => RuntimeException::class,
        'finished_at' => now(),
    ]);

    $message = $contact->outboxMessages()->create([
        'type' => OutboxMessage::TYPE_OWNER_EMAIL,
        'payload' => ['contact_id' => $contact->id],
        'attempts' => 10,
        'published_at' => now(),
        'failed_at' => now(),
        'last_error' => RuntimeException::class,
    ]);

    $this->artisan('contact:retry-failed', ['contactId' => $contact->id])
        ->expectsOutput("Retried 1 failed step(s) for contact [{$contact->id}].")
        ->assertSuccessful();

    expect($step->refresh())
        ->status->toBe(ContactStep::STATUS_PENDING)
        ->attempts->toBe(0)
        ->last_error->toBeNull()
        ->finished_at->toBeNull()
        ->and($message->refresh())
        ->attempts->toBe(0)
        ->published_at->toBeNull()
        ->failed_at->toBeNull()
        ->last_error->toBeNull()
        ->locked_at->toBeNull()
        ->locked_by->toBeNull()
        ->and($message->available_at->isPast())->toBeTrue();
});

test('processing job classifies once and creates email outbox messages', function () {
    $summary = 'Запрос на разработку веб-сервиса.';
    $suggestedReply = 'Спасибо за обращение! Готов обсудить требования к проекту.';

    Http::fake([
        'https://api.openai.test/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'category' => 'project',
                        'summary' => $summary,
                        'suggested_reply' => $suggestedReply,
                    ], JSON_THROW_ON_ERROR),
                ],
            ]],
        ]),
    ]);

    $contact = createContactForOutbox();
    $dependencies = [
        app(ContactRepository::class),
        app(ContactStepManager::class),
        app(OpenAiContactClassifier::class),
    ];

    (new ProcessContact($contact->id))->handle(...$dependencies);
    (new ProcessContact($contact->id))->handle(...$dependencies);

    $classification = $contact->steps()
        ->where('type', ContactStep::TYPE_CLASSIFICATION)
        ->sole();

    expect($contact->refresh())
        ->ai_category->toBe('project')
        ->ai_summary->toBe($summary)
        ->ai_suggested_reply->toBe($suggestedReply)
        ->and($classification->status)->toBe(ContactStep::STATUS_SUCCEEDED)
        ->and($classification->attempts)->toBe(1)
        ->and($contact->outboxMessages()->count())->toBe(3);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        $body = json_encode($request->data(), JSON_THROW_ON_ERROR);

        return $request['messages'][1]['content'] === 'Хочу обсудить разработку веб-сервиса.'
            && $request['response_format']['json_schema']['schema']['properties']['summary']['maxLength'] === 300
            && $request['response_format']['json_schema']['schema']['properties']['suggested_reply']['maxLength'] === 800
            && $request['provider']['require_parameters'] === true
            && $request['plugins'] === [['id' => 'response-healing']]
            && $request['stream'] === false
            && ! str_contains($body, 'artem@example.com')
            && ! str_contains($body, '+7 (999) 123-45-67');
    });
});

test('non-retryable AI client error falls back immediately', function () {
    Http::fake([
        'https://api.openai.test/v1/chat/completions' => Http::response([], 401),
    ]);

    $contact = createContactForOutbox();

    (new ProcessContact($contact->id))->handle(
        app(ContactRepository::class),
        app(ContactStepManager::class),
        app(OpenAiContactClassifier::class),
    );

    $classification = $contact->steps()
        ->where('type', ContactStep::TYPE_CLASSIFICATION)
        ->sole();

    expect($contact->refresh())
        ->ai_category->toBeNull()
        ->ai_summary->toBeNull()
        ->ai_suggested_reply->toBeNull()
        ->and($classification->status)->toBe(ContactStep::STATUS_FALLBACK)
        ->and($contact->outboxMessages()->count())->toBe(3);

    Http::assertSentCount(1);
});

test('missing AI key falls back without an HTTP request', function () {
    config(['services.openai.key' => null]);
    $contact = createContactForOutbox();

    (new ProcessContact($contact->id))->handle(
        app(ContactRepository::class),
        app(ContactStepManager::class),
        app(OpenAiContactClassifier::class),
    );

    $classification = $contact->steps()
        ->where('type', ContactStep::TYPE_CLASSIFICATION)
        ->sole();

    expect($classification->status)->toBe(ContactStep::STATUS_FALLBACK)
        ->and($contact->outboxMessages()->count())->toBe(3);

    Http::assertNothingSent();
});

test('transient AI responses leave classification open for queue retry', function (int $status) {
    Http::fake([
        'https://api.openai.test/v1/chat/completions' => Http::response([], $status),
    ]);

    $contact = createContactForOutbox();

    expect(fn () => (new ProcessContact($contact->id))->handle(
        app(ContactRepository::class),
        app(ContactStepManager::class),
        app(OpenAiContactClassifier::class),
    ))->toThrow(RequestException::class);

    $classification = $contact->steps()
        ->where('type', ContactStep::TYPE_CLASSIFICATION)
        ->sole();

    expect($classification)
        ->status->toBe(ContactStep::STATUS_PROCESSING)
        ->attempts->toBe(1)
        ->and($contact->outboxMessages()->count())->toBe(1);

    Http::assertSentCount(1);
})->with([
    'request timeout' => 408,
    'rate limited' => 429,
    'provider error' => 500,
]);

test('AI connection errors leave classification open for queue retry', function () {
    Http::fake([
        'https://api.openai.test/v1/chat/completions' => Http::failedConnection(),
    ]);

    $contact = createContactForOutbox();

    expect(fn () => (new ProcessContact($contact->id))->handle(
        app(ContactRepository::class),
        app(ContactStepManager::class),
        app(OpenAiContactClassifier::class),
    ))->toThrow(ConnectionException::class);

    $classification = $contact->steps()
        ->where('type', ContactStep::TYPE_CLASSIFICATION)
        ->sole();

    expect($classification)
        ->status->toBe(ContactStep::STATUS_PROCESSING)
        ->attempts->toBe(1)
        ->and($contact->outboxMessages()->count())->toBe(1);

    Http::assertSentCount(1);
});

test('exhausted AI retries fall back and enqueue both emails', function () {
    $contact = createContactForOutbox();
    $job = new ProcessContact($contact->id);

    expect(app(ContactStepManager::class)->claim(
        $contact->id,
        ContactStep::TYPE_CLASSIFICATION,
        $job->workerId,
    ))->toBeTrue();

    $job->failed(new RuntimeException('AI unavailable'));

    $classification = $contact->steps()
        ->where('type', ContactStep::TYPE_CLASSIFICATION)
        ->sole();

    expect($classification)
        ->status->toBe(ContactStep::STATUS_FALLBACK)
        ->last_error->toBe(RuntimeException::class)
        ->locked_by->toBeNull()
        ->and($contact->outboxMessages()->count())->toBe(3);
});

test('invalid AI analysis falls back without saving partial data', function (array $analysis) {
    Http::fake([
        'https://api.openai.test/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode($analysis, JSON_THROW_ON_ERROR),
                ],
            ]],
        ]),
    ]);

    $contact = createContactForOutbox();

    (new ProcessContact($contact->id))->handle(
        app(ContactRepository::class),
        app(ContactStepManager::class),
        app(OpenAiContactClassifier::class),
    );

    $classification = $contact->steps()
        ->where('type', ContactStep::TYPE_CLASSIFICATION)
        ->sole();

    expect($contact->refresh())
        ->ai_category->toBeNull()
        ->ai_summary->toBeNull()
        ->ai_suggested_reply->toBeNull()
        ->and($classification->status)->toBe(ContactStep::STATUS_FALLBACK)
        ->and($contact->outboxMessages()->count())->toBe(3);
})->with([
    'required field is missing' => [[
        'category' => 'project',
        'summary' => 'Запрос на разработку.',
    ]],
    'summary is too long' => [[
        'category' => 'project',
        'summary' => str_repeat('а', 301),
        'suggested_reply' => 'Спасибо за обращение.',
    ]],
    'suggested reply is too long' => [[
        'category' => 'project',
        'summary' => 'Запрос на разработку.',
        'suggested_reply' => str_repeat('а', 801),
    ]],
]);

test('email jobs send each message once and record statuses', function () {
    $contact = createContactForOutbox();
    $contacts = app(ContactRepository::class);
    $steps = app(ContactStepManager::class);

    (new SendContactOwnerEmail($contact->id))->handle($contacts, $steps);
    (new SendContactOwnerEmail($contact->id))->handle($contacts, $steps);
    (new SendContactConfirmationEmail($contact->id))->handle($contacts, $steps);
    (new SendContactConfirmationEmail($contact->id))->handle($contacts, $steps);

    Mail::assertSent(
        ContactReceivedForOwner::class,
        fn (ContactReceivedForOwner $mail): bool => $mail->hasTo('owner@example.com'),
    );
    Mail::assertSent(
        ContactReceivedConfirmation::class,
        fn (ContactReceivedConfirmation $mail): bool => $mail->hasTo('artem@example.com'),
    );
    Mail::assertSentCount(2);

    expect($contact->steps()
        ->whereIn('type', [
            ContactStep::TYPE_OWNER_EMAIL,
            ContactStep::TYPE_CONFIRMATION_EMAIL,
        ])
        ->pluck('status')
        ->unique()
        ->all())->toBe([ContactStep::STATUS_SENT]);
});

test('relay publishes independent email jobs', function () {
    $contact = createContactForOutbox();

    $contact->outboxMessages()->update(['published_at' => now()]);
    $contact->outboxMessages()->createMany([
        [
            'type' => OutboxMessage::TYPE_OWNER_EMAIL,
            'payload' => ['contact_id' => $contact->id],
        ],
        [
            'type' => OutboxMessage::TYPE_CONFIRMATION_EMAIL,
            'payload' => ['contact_id' => $contact->id],
        ],
    ]);

    expect(app(OutboxPublisher::class)->publishBatch())->toBe(2);

    Queue::assertPushedOn('mail', SendContactOwnerEmail::class);
    Queue::assertPushedOn('mail', SendContactConfirmationEmail::class);
});

test('step lease prevents concurrent duplicate processing', function () {
    $contact = createContactForOutbox();
    $steps = app(ContactStepManager::class);

    expect($steps->claim(
        $contact->id,
        ContactStep::TYPE_OWNER_EMAIL,
        '11111111-1111-4111-8111-111111111111',
    ))->toBeTrue()
        ->and($steps->claim(
            $contact->id,
            ContactStep::TYPE_OWNER_EMAIL,
            '22222222-2222-4222-8222-222222222222',
        ))->toBeFalse();

    $steps->complete(
        $contact->id,
        ContactStep::TYPE_OWNER_EMAIL,
        '11111111-1111-4111-8111-111111111111',
        ContactStep::STATUS_SENT,
    );

    expect($steps->claim(
        $contact->id,
        ContactStep::TYPE_OWNER_EMAIL,
        '22222222-2222-4222-8222-222222222222',
    ))->toBeFalse();
});
