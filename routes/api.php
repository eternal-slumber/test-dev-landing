<?php

use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('api.health');

Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact');
