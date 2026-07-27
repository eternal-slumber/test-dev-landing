<?php

namespace App\Services;

use App\DTO\ContactClassificationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class OpenAiContactClassifier
{
    private const array CATEGORIES = [
        'project',
        'employment',
        'consultation',
        'other',
    ];

    public function classify(string $comment): ContactClassificationResult
    {
        $apiKey = config('services.openai.key');

        if (! is_string($apiKey) || $apiKey === '') {
            Log::warning('OpenAI contact classification skipped: API key is not configured.');

            return new ContactClassificationResult(null);
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->connectTimeout((int) config('services.openai.connect_timeout'))
                ->timeout((int) config('services.openai.timeout'))
                ->retry(
                    2,
                    100,
                    fn (Throwable $exception): bool => $exception instanceof ConnectionException
                        || ($exception instanceof RequestException && $exception->response->serverError()),
                    throw: false,
                )
                ->post((string) config('services.openai.url'), [
                    'model' => config('services.openai.model'),
                    'store' => false,
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => 'Classify the contact request as project, employment, consultation, or other.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $comment,
                        ],
                    ],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'contact_classification',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'category' => [
                                        'type' => 'string',
                                        'enum' => self::CATEGORIES,
                                    ],
                                ],
                                'required' => ['category'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                ]);
        } catch (ConnectionException) {
            Log::warning('OpenAI contact classification failed: connection error.');

            return new ContactClassificationResult(null);
        }

        if (! $response->successful()) {
            Log::warning('OpenAI contact classification failed.', [
                'status' => $response->status(),
            ]);

            return new ContactClassificationResult(null);
        }

        $category = $this->extractCategory($response);

        if ($category === null) {
            Log::warning('OpenAI contact classification failed: invalid response.');
        }

        return new ContactClassificationResult($category);
    }

    private function extractCategory(Response $response): ?string
    {
        foreach ((array) $response->json('output', []) as $output) {
            foreach ((array) data_get($output, 'content', []) as $content) {
                if (data_get($content, 'type') !== 'output_text') {
                    continue;
                }

                $decoded = json_decode((string) data_get($content, 'text'), true);
                $category = is_array($decoded) ? ($decoded['category'] ?? null) : null;

                return is_string($category) && in_array($category, self::CATEGORIES, true)
                    ? $category
                    : null;
            }
        }

        return null;
    }
}
