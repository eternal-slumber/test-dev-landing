<?php

use Illuminate\Support\Facades\Schema;

test('authentication routes are not exposed', function () {
    $this->get('/login')->assertNotFound();
    $this->post('/login')->assertNotFound();
    $this->get('/register')->assertNotFound();
    $this->get('/forgot-password')->assertNotFound();
    $this->get('/dashboard')->assertNotFound();
    $this->get('/settings/profile')->assertNotFound();
});

test('authentication tables are not created', function () {
    expect(Schema::hasTable('users'))->toBeFalse()
        ->and(Schema::hasTable('password_reset_tokens'))->toBeFalse()
        ->and(Schema::hasTable('sessions'))->toBeTrue();
});
