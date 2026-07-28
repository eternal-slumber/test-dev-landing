<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $services = [
            'database' => $this->probe(fn () => DB::selectOne('select 1')),
            'redis' => $this->probe(fn () => Redis::connection()->ping()),
            'ai' => $this->configurationStatus(
                'services.openai.key',
                'services.openai.model',
                'services.openai.url',
            ),
            'mail' => $this->mailStatus(),
        ];
        $status = $this->isOperational($services) ? 'ok' : 'degraded';

        return response()
            ->json([
                'status' => $status,
                'services' => $services,
                'timestamp' => now()->utc()->toIso8601String(),
            ], $status === 'ok' ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }

    /**
     * @param  callable(): mixed  $probe
     */
    private function probe(callable $probe): string
    {
        try {
            $probe();

            return 'ok';
        } catch (Throwable) {
            return 'error';
        }
    }

    private function configurationStatus(string ...$keys): string
    {
        foreach ($keys as $key) {
            $value = config($key);

            if (! is_string($value) || trim($value) === '') {
                return 'not_configured';
            }
        }

        return 'configured';
    }

    private function mailStatus(): string
    {
        $mailer = config('mail.default');

        if (
            ! is_string($mailer)
            || ! is_array(config("mail.mailers.{$mailer}"))
        ) {
            return 'not_configured';
        }

        return $this->configurationStatus(
            'mail.contact_owner',
            'mail.from.address',
        );
    }

    /**
     * @param  array{
     *     database: string,
     *     redis: string,
     *     ai: string,
     *     mail: string
     * }  $services
     */
    private function isOperational(array $services): bool
    {
        return $services['database'] === 'ok'
            && $services['redis'] === 'ok'
            && $services['ai'] === 'configured'
            && $services['mail'] === 'configured';
    }
}
