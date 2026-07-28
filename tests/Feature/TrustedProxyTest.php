<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get(
        '/api/testing/request-metadata',
        fn (Request $request): array => [
            'ip' => $request->ip(),
            'host' => $request->getHost(),
            'origin' => $request->getSchemeAndHttpHost(),
        ],
    );
});

test('ignores forwarded headers from an untrusted client', function () {
    Log::shouldReceive('channel')->once()->with('requests')->andReturnSelf();
    Log::shouldReceive('info')->once()->with('HTTP request', Mockery::on(
        fn (array $context): bool => $context['ip'] === '203.0.113.10',
    ));

    $this
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeaders([
            'X-Forwarded-For' => '198.51.100.9',
            'X-Forwarded-Host' => 'attacker.example',
            'X-Forwarded-Proto' => 'https',
        ])
        ->getJson('/api/testing/request-metadata')
        ->assertOk()
        ->assertJson([
            'ip' => '203.0.113.10',
            'host' => 'localhost',
            'origin' => 'http://localhost:8000',
        ]);
});

test('accepts forwarded ip and scheme from a trusted proxy', function () {
    $this
        ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeaders([
            'X-Forwarded-For' => '198.51.100.9',
            'X-Forwarded-Proto' => 'https',
        ])
        ->getJson('/api/testing/request-metadata')
        ->assertOk()
        ->assertJson([
            'ip' => '198.51.100.9',
            'host' => 'localhost',
            'origin' => 'https://localhost:8000',
        ]);
});

test('trusted proxy cannot override host through forwarded headers', function () {
    $this
        ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeaders([
            'X-Forwarded-Host' => 'attacker.example',
        ])
        ->getJson('/api/testing/request-metadata')
        ->assertOk()
        ->assertJson([
            'host' => 'localhost',
            'origin' => 'http://localhost:8000',
        ]);
});
