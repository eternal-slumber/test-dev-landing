<?php

use App\Jobs\ProcessContact;
use App\Models\Contact;
use App\Models\ContactStep;
use App\Models\OutboxMessage;
use App\Services\ContactService;
use App\Services\OutboxPublisher;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * @return array{
 *     name: string,
 *     phone: string,
 *     email: string,
 *     comment: string,
 *     idempotency_key: string
 * }
 */
function postgresContactPayload(string $idempotencyKey): array
{
    return [
        'name' => 'Артем',
        'phone' => '+7 (999) 123-45-67',
        'email' => 'artem@example.com',
        'comment' => 'Хочу обсудить разработку веб-сервиса.',
        'idempotency_key' => $idempotencyKey,
    ];
}

/** @param array<string, mixed> $payload */
function createPostgresContactTask(array $payload): Closure
{
    return static fn (): int => app(ContactService::class)->create($payload)->id;
}

test('contact and outbox are committed or rolled back together', function () {
    OutboxMessage::creating(function (): never {
        throw new RuntimeException('Outbox unavailable');
    });

    expect(fn () => app(ContactService::class)->create(
        postgresContactPayload((string) Str::uuid()),
    ))->toThrow(RuntimeException::class, 'Outbox unavailable');

    expect(Contact::query()->count())->toBe(0)
        ->and(ContactStep::query()->count())->toBe(0)
        ->and(OutboxMessage::query()->count())->toBe(0);
});

test('concurrent requests with one idempotency key create one contact', function () {
    $payload = postgresContactPayload((string) Str::uuid());

    $contactIds = Concurrency::run([
        createPostgresContactTask($payload),
        createPostgresContactTask($payload),
    ]);

    expect($contactIds[0])->toBe($contactIds[1])
        ->and(Contact::query()
            ->where('idempotency_key', $payload['idempotency_key'])
            ->count())->toBe(1)
        ->and(ContactStep::query()
            ->where('contact_id', $contactIds[0])
            ->count())->toBe(3)
        ->and(OutboxMessage::query()
            ->where('contact_id', $contactIds[0])
            ->count())->toBe(1);
});

test('outbox relay skips a message locked by another worker', function () {
    $firstContact = app(ContactService::class)->create(
        postgresContactPayload((string) Str::uuid()),
    );
    $secondContact = app(ContactService::class)->create(
        postgresContactPayload((string) Str::uuid()),
    );

    $firstMessage = $firstContact->outboxMessages()->sole();
    $secondMessage = $secondContact->outboxMessages()->sole();

    OutboxMessage::query()
        ->whereKey([$firstMessage->id, $secondMessage->id])
        ->update(['available_at' => now()->subSecond()]);

    config([
        'database.connections.pgsql_lock' => config('database.connections.pgsql'),
    ]);

    $lockConnection = DB::connection('pgsql_lock');
    $lockConnection->beginTransaction();

    try {
        $lockConnection
            ->table('outbox_messages')
            ->where('id', $firstMessage->id)
            ->lockForUpdate()
            ->first();

        Queue::fake();

        expect(app(OutboxPublisher::class)->publishBatch(1))->toBe(1)
            ->and($firstMessage->refresh()->published_at)->toBeNull()
            ->and($secondMessage->refresh()->published_at)->not->toBeNull();

        Queue::assertPushed(
            ProcessContact::class,
            fn (ProcessContact $job): bool => $job->contactId === $secondContact->id,
        );
    } finally {
        $lockConnection->rollBack();
        DB::purge('pgsql_lock');
    }
});
