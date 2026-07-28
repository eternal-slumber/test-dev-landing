<?php

namespace App\Services;

use App\DTO\ContactAnalysisResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class OpenAiContactClassifier
{
    private const array CATEGORIES = [
        'project',
        'employment',
        'consultation',
        'other',
    ];

    private const int SUMMARY_MAX_LENGTH = 300;

    private const int SUGGESTED_REPLY_MAX_LENGTH = 800;

    public function classify(string $comment): ContactAnalysisResult
    {
        $apiKey = config('services.openai.key');

        if (! is_string($apiKey) || $apiKey === '') {
            Log::warning(
                'OpenAI contact analysis skipped: API key is not configured.',
            );

            return ContactAnalysisResult::unavailable();
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->connectTimeout(
                    (int) config('services.openai.connect_timeout'),
                )
                ->timeout(
                    (int) config('services.openai.timeout'),
                )
                ->post(
                    (string) config('services.openai.url'),
                    [
                        'model' => config('services.openai.model'),
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => <<<'PROMPT'
Analyze the contact request and return ONLY a valid JSON object with exactly these keys:
{"category":"project|employment|consultation|other","summary":"...","suggested_reply":"..."}

Use the same language as the user's comment.
Keep summary within 300 characters and suggested_reply within 800 characters.
Do not invent facts, prices, deadlines, guarantees, or obligations.
Do not claim that the request or project has been accepted.
The suggested reply is only a draft for the owner.
Do not use Markdown, code fences, bullet lists, or text outside the JSON object.
PROMPT,
                            ],
                            [
                                'role' => 'user',
                                'content' => $comment,
                            ],
                        ],
                        'response_format' => [
                            'type' => 'json_schema',
                            'json_schema' => [
                                'name' => 'contact_analysis',
                                'strict' => true,
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'category' => [
                                            'type' => 'string',
                                            'description' => 'One contact request category.',
                                            'enum' => self::CATEGORIES,
                                        ],
                                        'summary' => [
                                            'type' => 'string',
                                            'description' => 'A concise summary in the comment language.',
                                            'maxLength' => self::SUMMARY_MAX_LENGTH,
                                        ],
                                        'suggested_reply' => [
                                            'type' => 'string',
                                            'description' => 'A polite draft reply in the comment language.',
                                            'maxLength' => self::SUGGESTED_REPLY_MAX_LENGTH,
                                        ],
                                    ],
                                    'required' => [
                                        'category',
                                        'summary',
                                        'suggested_reply',
                                    ],
                                    'additionalProperties' => false,
                                ],
                            ],
                        ],
                        'provider' => [
                            'require_parameters' => true,
                        ],
                        'plugins' => [
                            ['id' => 'response-healing'],
                        ],
                        'stream' => false,
                    ],
                );
        } catch (ConnectionException $exception) {
            Log::warning(
                'OpenAI contact analysis failed: connection error.',
            );

            throw $exception;
        }

        if (
            $response->status() === 408
            || $response->status() === 429
            || $response->serverError()
        ) {
            Log::warning('OpenAI contact analysis will be retried.', [
                'status' => $response->status(),
            ]);

            $response->throw();
        }

        if (! $response->successful()) {
            Log::warning('OpenAI contact analysis skipped.', [
                'status' => $response->status(),
            ]);

            return ContactAnalysisResult::unavailable();
        }

        $analysis = $this->extractAnalysis($response);

        if (! $analysis->isAvailable()) {
            Log::warning(
                'OpenAI contact analysis failed: invalid response.',
            );
        }

        return $analysis;
    }

    private function extractAnalysis(
        Response $response,
    ): ContactAnalysisResult {
        $content = $response->json('choices.0.message.content');

        if (! is_string($content)) {
            return ContactAnalysisResult::unavailable();
        }

        return $this->decodeAnalysis($content);
    }

    private function decodeAnalysis(string $json): ContactAnalysisResult
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return ContactAnalysisResult::unavailable();
        }

        $category = $decoded['category'] ?? null;
        $summary = $decoded['summary'] ?? null;
        $suggestedReply = $decoded['suggested_reply'] ?? null;

        if (
            ! is_string($category)
            || ! in_array($category, self::CATEGORIES, true)
            || ! is_string($summary)
            || ! is_string($suggestedReply)
        ) {
            return ContactAnalysisResult::unavailable();
        }

        $summary = trim($summary);
        $suggestedReply = trim($suggestedReply);

        if (
            $summary === ''
            || $suggestedReply === ''
            || Str::length($summary) > self::SUMMARY_MAX_LENGTH
            || Str::length($suggestedReply) > self::SUGGESTED_REPLY_MAX_LENGTH
        ) {
            return ContactAnalysisResult::unavailable();
        }

        return new ContactAnalysisResult(
            category: $category,
            summary: $summary,
            suggestedReply: $suggestedReply,
        );
    }
}
