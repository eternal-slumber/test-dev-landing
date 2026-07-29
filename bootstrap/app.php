<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequestLogger;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            headers: SymfonyRequest::HEADER_X_FORWARDED_FOR
                | SymfonyRequest::HEADER_X_FORWARDED_PROTO,
        );
        $middleware->prepend(RequestLogger::class);

        $middleware->encryptCookies(except: ['appearance']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $_exception): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response): Response {
            if (! request()->is('api/*')) {
                return $response;
            }

            $requestId = Context::get('request_id');

            if (! is_string($requestId)) {
                $requestId = (string) Str::uuid();
                Context::add('request_id', $requestId);
            }

            $response->headers->set('X-Request-ID', $requestId);

            return $response;
        });
    })->create();
