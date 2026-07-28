<?php

use App\Models\Contact;
use App\Models\ContactStep;
use App\Models\OutboxMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\get;
use function Pest\Laravel\options;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withServerVariables;

/**
 * @param  array<string, mixed>  $payload
 */
function postContactRequest(array $payload, ?string $idempotencyKey = null): TestResponse
{
    return postJson('/api/contact', $payload, [
        'Idempotency-Key' => $idempotencyKey ?? (string) Str::uuid(),
    ]);
}

beforeEach(function () {
    config([
        'logging.channels.api' => config('logging.channels.null'),
    ]);

    Http::preventStrayRequests();
    Mail::fake();
    Queue::fake();
});

test('valid contact request is accepted and stored', function () {
    Log::shouldReceive('channel')->once()->with('api')->andReturnSelf();
    Log::shouldReceive('info')->once()->with('API request', Mockery::on(
        fn (array $context): bool => Str::isUuid($context['request_id'])
            && $context['method'] === 'POST'
            && $context['path'] === 'api/contact'
            && $context['status'] === 201
            && is_numeric($context['duration_ms'])
            && is_int($context['contact_id'])
            && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'artem@example.com')
            && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), '+7 999 123-45-67'),
    ));

    $response = postContactRequest([
        'name' => 'Артем',
        'phone' => '+7 999 123-45-67',
        'email' => 'artem@example.com',
        'comment' => 'Хочу обсудить разработку веб-сервиса.',
    ]);

    $contact = Contact::query()->sole();

    $response
        ->assertCreated()
        ->assertHeader('X-Request-ID')
        ->assertJson([
            'data' => [
                'id' => $contact->id,
                'status' => 'accepted',
            ],
            'message' => 'Обращение принято',
        ]);

    expect($contact->ai_category)->toBeNull()
        ->and($contact->steps()->count())->toBe(3)
        ->and($contact->steps()->pluck('status')->unique()->all())
        ->toBe([ContactStep::STATUS_PENDING])
        ->and($contact->outboxMessages()->sole()->type)
        ->toBe(OutboxMessage::TYPE_PROCESS_CONTACT);

    Http::assertNothingSent();
    Mail::assertNothingSent();
    Queue::assertNothingPushed();
});

test('contact input is normalized before storage', function () {
    postContactRequest([
        'name' => "\tStudio\n42\r",
        'phone' => "\t+7 (999)\n123-45-67\r",
        'email' => '  ARTEM@EXAMPLE.COM  ',
        'comment' => "  Хочу обсудить разработку веб-сервиса.\n",
    ])->assertCreated();

    $contact = Contact::query()->sole();

    expect($contact->name)->toBe('Studio 42')
        ->and($contact->phone)->toBe('+7 (999) 123-45-67')
        ->and($contact->email)->toBe('artem@example.com')
        ->and($contact->comment)->toBe('Хочу обсудить разработку веб-сервиса.');
});

test('invalid contact request is rejected and not stored', function () {
    Log::shouldReceive('channel')->once()->with('api')->andReturnSelf();
    Log::shouldReceive('info')->once()->with('API request', Mockery::on(
        fn (array $context): bool => Str::isUuid($context['request_id'])
            && $context['method'] === 'POST'
            && $context['path'] === 'api/contact'
            && $context['status'] === 422
            && $context['contact_id'] === null
            && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'artem@example.com')
            && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), '+7 999 123-45-67'),
    ));

    $response = postContactRequest([
        'name' => 'Артем',
        'phone' => '+7 999 123-45-67',
        'email' => 'invalid-email',
        'comment' => 'Хочу обсудить разработку веб-сервиса.',
    ]);

    $response
        ->assertUnprocessable()
        ->assertHeader('X-Request-ID')
        ->assertHeaderContains('Content-Type', 'application/json')
        ->assertJsonValidationErrors('email');

    expect(Contact::query()->exists())->toBeFalse();
    Http::assertNothingSent();
    Mail::assertNothingSent();
});

test('contact and outbox are rolled back together', function () {
    Event::listen(
        'eloquent.creating: '.OutboxMessage::class,
        fn () => throw new RuntimeException('Outbox insert failed'),
    );
    Exceptions::fake();

    Log::shouldReceive('channel')->once()->with('api')->andReturnSelf();
    Log::shouldReceive('info')->once()->with('API request', Mockery::on(
        fn (array $context): bool => $context['status'] === 500
            && $context['contact_id'] === null,
    ));

    try {
        postContactRequest([
            'name' => 'Артем',
            'phone' => '+7 999 123-45-67',
            'email' => 'artem@example.com',
            'comment' => 'Хочу обсудить разработку веб-сервиса.',
        ])
            ->assertInternalServerError()
            ->assertHeader('X-Request-ID');
    } finally {
        Event::forget('eloquent.creating: '.OutboxMessage::class);
    }

    expect(Contact::query()->exists())->toBeFalse()
        ->and(OutboxMessage::query()->exists())->toBeFalse();
    Exceptions::assertReported(RuntimeException::class);
});

test('contact endpoint is rate limited by ip', function () {
    config(['app.contact_rate_limit' => 2]);

    $payload = [
        'name' => 'Артем',
        'phone' => '+7 999 123-45-67',
        'email' => 'artem@example.com',
        'comment' => 'Хочу обсудить разработку веб-сервиса.',
    ];

    withServerVariables([
        'REMOTE_ADDR' => '203.0.113.10',
    ]);

    postContactRequest($payload)->assertCreated();
    postContactRequest($payload)->assertCreated();

    $response = postContactRequest($payload);

    $response
        ->assertTooManyRequests()
        ->assertHeader('X-Request-ID')
        ->assertHeaderContains('Content-Type', 'application/json')
        ->assertHeader('X-RateLimit-Limit', '2')
        ->assertHeader('X-RateLimit-Remaining', '0');

    $retryAfter = $response->headers->get('Retry-After');

    expect($retryAfter)->not->toBeNull()
        ->and(is_numeric($retryAfter))->toBeTrue()
        ->and((int) $retryAfter)->toBeGreaterThan(0)
        ->and(Contact::query()->count())->toBe(2)
        ->and(OutboxMessage::query()->count())->toBe(2);

    Http::assertNothingSent();
    Mail::assertNothingSent();
});

test('contact rate limit uses client ip forwarded by trusted proxy', function () {
    config(['app.contact_rate_limit' => 1]);

    $payload = [
        'name' => 'Артем',
        'phone' => '+7 999 123-45-67',
        'email' => 'artem@example.com',
        'comment' => 'Хочу обсудить разработку веб-сервиса.',
    ];

    withServerVariables([
        'REMOTE_ADDR' => '127.0.0.1',
    ]);

    postJson('/api/contact', $payload, [
        'Idempotency-Key' => (string) Str::uuid(),
        'X-Forwarded-For' => '198.51.100.1, 203.0.113.10',
    ])->assertCreated();

    postJson('/api/contact', $payload, [
        'Idempotency-Key' => (string) Str::uuid(),
        'X-Forwarded-For' => '198.51.100.1, 203.0.113.11',
    ])->assertCreated();

    postJson('/api/contact', $payload, [
        'Idempotency-Key' => (string) Str::uuid(),
        'X-Forwarded-For' => '198.51.100.2, 203.0.113.10',
    ])->assertTooManyRequests();

    expect(Contact::query()->count())->toBe(2);
});

test('retry with the same idempotency key returns the original contact', function () {
    $payload = [
        'name' => 'Артем',
        'phone' => '+7 999 123-45-67',
        'email' => 'artem@example.com',
        'comment' => 'Хочу обсудить разработку веб-сервиса.',
    ];
    $idempotencyKey = (string) Str::uuid();

    $firstResponse = postContactRequest($payload, $idempotencyKey)->assertCreated();
    $retryResponse = postContactRequest($payload, $idempotencyKey)->assertCreated();

    expect(Contact::query()->count())->toBe(1)
        ->and(OutboxMessage::query()->count())->toBe(1)
        ->and(ContactStep::query()->count())->toBe(3)
        ->and($retryResponse->json('data.id'))->toBe($firstResponse->json('data.id'));

    Http::assertNothingSent();
    Mail::assertNothingSent();
});

test('reusing an idempotency key for another payload is rejected', function () {
    $idempotencyKey = (string) Str::uuid();
    $payload = [
        'name' => 'Артем',
        'phone' => '+7 999 123-45-67',
        'email' => 'artem@example.com',
        'comment' => 'Хочу обсудить разработку веб-сервиса.',
    ];

    postContactRequest($payload, $idempotencyKey)->assertCreated();

    postContactRequest([
        ...$payload,
        'comment' => 'Это уже другое обращение пользователя.',
    ], $idempotencyKey)
        ->assertConflict()
        ->assertHeader('X-Request-ID')
        ->assertJson([
            'message' => 'Idempotency-Key уже использован для другого обращения.',
        ]);

    expect(Contact::query()->count())->toBe(1);
    Http::assertNothingSent();
    Mail::assertNothingSent();
});

test('idempotency key header is required', function () {
    postJson('/api/contact', [
        'name' => 'Артем',
        'phone' => '+7 999 123-45-67',
        'email' => 'artem@example.com',
        'comment' => 'Хочу обсудить разработку веб-сервиса.',
    ])
        ->assertUnprocessable()
        ->assertHeader('X-Request-ID')
        ->assertJsonValidationErrors('idempotency_key');

    expect(Contact::query()->exists())->toBeFalse();
    Http::assertNothingSent();
    Mail::assertNothingSent();
});

test('missing api route is logged and returned as json', function () {
    Log::shouldReceive('channel')->once()->with('api')->andReturnSelf();
    Log::shouldReceive('info')->once()->with('API request', Mockery::on(
        fn (array $context): bool => Str::isUuid($context['request_id'])
            && $context['method'] === 'GET'
            && $context['path'] === 'api/missing'
            && $context['status'] === 404
            && is_numeric($context['duration_ms'])
            && $context['contact_id'] === null,
    ));

    get('/api/missing')
        ->assertNotFound()
        ->assertHeader('X-Request-ID')
        ->assertHeaderContains('Content-Type', 'application/json')
        ->assertJsonStructure(['message']);
});

test('unsupported api method is returned as json', function () {
    get('/api/contact')
        ->assertMethodNotAllowed()
        ->assertHeader('X-Request-ID')
        ->assertHeaderContains('Content-Type', 'application/json')
        ->assertJsonStructure(['message']);
});

test('unexpected api error is returned safely as json', function () {
    config(['app.debug' => false]);
    Exceptions::fake();

    Route::get('/api/test-error', fn () => throw new RuntimeException(
        'Sensitive internal information',
    ));

    get('/api/test-error')
        ->assertInternalServerError()
        ->assertHeader('X-Request-ID')
        ->assertHeaderContains('Content-Type', 'application/json')
        ->assertJson([
            'message' => 'Server Error',
        ])
        ->assertJsonMissingPath('exception')
        ->assertDontSee('Sensitive internal information');

    Exceptions::assertReported(RuntimeException::class);
});

test('cors allows configured frontend origin', function () {
    config([
        'cors.allowed_origins' => ['https://frontend.example'],
    ]);

    options('/api/contact', headers: [
        'Origin' => 'https://frontend.example',
        'Access-Control-Request-Method' => 'POST',
    ])
        ->assertNoContent()
        ->assertHeader('X-Request-ID')
        ->assertHeader(
            'Access-Control-Allow-Origin',
            'https://frontend.example',
        )
        ->assertHeaderContains(
            'Access-Control-Allow-Methods',
            'POST',
        );
});

test('cors does not allow unconfigured origin', function () {
    config([
        'cors.allowed_origins' => ['https://frontend.example'],
    ]);

    options('/api/contact', headers: [
        'Origin' => 'https://evil.example',
        'Access-Control-Request-Method' => 'POST',
    ])
        ->assertNoContent()
        ->assertHeader('X-Request-ID')
        ->assertHeader(
            'Access-Control-Allow-Origin',
            'https://frontend.example',
        );
});
