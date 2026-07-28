<?php

use Illuminate\Support\Facades\Redis;

use function Pest\Laravel\getJson;

beforeEach(function () {
    config([
        'services.openai.key' => 'test-key',
        'services.openai.model' => 'openrouter/free',
        'services.openai.url' => 'https://openrouter.ai/api/v1/chat/completions',
        'mail.default' => 'array',
        'mail.contact_owner' => 'owner@example.com',
        'mail.from.address' => 'noreply@example.com',
    ]);
});

test('health endpoint reports configured services', function () {
    $this->travelTo('2026-07-28 15:30:00 UTC');

    $redis = Mockery::mock();
    $redis->shouldReceive('ping')->once()->andReturnTrue();
    Redis::shouldReceive('connection')->once()->andReturn($redis);

    getJson(route('api.health'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson([
            'status' => 'ok',
            'services' => [
                'database' => 'ok',
                'redis' => 'ok',
                'ai' => 'configured',
                'mail' => 'configured',
            ],
            'timestamp' => '2026-07-28T15:30:00+00:00',
        ]);
});

test('health endpoint reports degraded infrastructure', function () {
    Redis::shouldReceive('connection')
        ->once()
        ->andThrow(new RuntimeException('Redis unavailable'));

    getJson(route('api.health'))
        ->assertServiceUnavailable()
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('services.database', 'ok')
        ->assertJsonPath('services.redis', 'error')
        ->assertJsonPath('services.ai', 'configured')
        ->assertJsonPath('services.mail', 'configured');
});
