<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('renders the landing page', function () {
    Log::shouldReceive('channel')->once()->with('requests')->andReturnSelf();
    Log::shouldReceive('info')->once()->with('HTTP request', Mockery::on(
        fn (array $context): bool => Str::isUuid($context['request_id'])
            && $context['method'] === 'GET'
            && $context['path'] === '/'
            && $context['status'] === 200
            && is_numeric($context['duration_ms'])
            && $context['contact_id'] === null,
    ));

    $this->get(route('home'))
        ->assertOk()
        ->assertHeader('X-Request-ID')
        ->assertInertia(
            fn (Assert $page) => $page->component('welcome'),
        );
});

test('renders the system status page', function () {
    Log::shouldReceive('channel')->once()->with('requests')->andReturnSelf();
    Log::shouldReceive('info')->once()->with('HTTP request', Mockery::on(
        fn (array $context): bool => Str::isUuid($context['request_id'])
            && $context['method'] === 'GET'
            && $context['path'] === 'status'
            && $context['status'] === 200
            && is_numeric($context['duration_ms'])
            && $context['contact_id'] === null,
    ));

    $this->get(route('status'))
        ->assertOk()
        ->assertHeader('X-Request-ID')
        ->assertInertia(
            fn (Assert $page) => $page->component('status'),
        );
});
