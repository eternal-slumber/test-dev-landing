<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class RequestLogger
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        $startedAt = (int) hrtime(true);
        $status = 500;

        Context::forget('contact_id');
        Context::add('request_id', $requestId);

        try {
            $response = $next($request);
            $status = $response->getStatusCode();

            $response->headers->set('X-Request-ID', $requestId);

            return $response;
        } catch (Throwable $exception) {
            $status = $this->resolveStatus($exception);

            throw $exception;
        } finally {
            $this->writeLog($request, $requestId, $status, $startedAt);
        }
    }

    private function resolveStatus(Throwable $exception): int
    {
        return match (true) {
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            $exception instanceof ValidationException => $exception->status,
            default => 500,
        };
    }

    private function writeLog(
        Request $request,
        string $requestId,
        int $status,
        int $startedAt,
    ): void {
        try {
            Log::channel('requests')->info('HTTP request', [
                'request_id' => $requestId,
                'method' => $request->method(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'status' => $status,
                'duration_ms' => round(
                    (hrtime(true) - $startedAt) / 1_000_000, 2,
                ),
                'contact_id' => Context::get('contact_id'),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
