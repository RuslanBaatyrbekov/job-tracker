<?php

namespace App\Services\Ai;

use App\Contracts\AiClient;
use App\Exceptions\AiRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class GeminiClient implements AiClient
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly int $timeout = 60,
    ) {}

    public function json(string $prompt): array
    {
        $raw = $this->generate($prompt, ['response_mime_type' => 'application/json']);

        // Models sometimes wrap JSON in ```json fences even in JSON mode.
        $raw = trim(preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($raw)));
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw new AiRequestException('Gemini returned invalid JSON: '.mb_substr($raw, 0, 300));
        }

        return $decoded;
    }

    public function text(string $prompt): string
    {
        return trim($this->generate($prompt));
    }

    private function generate(string $prompt, array $generationConfig = []): string
    {
        if (! $this->apiKey) {
            throw new AiRequestException('GEMINI_API_KEY is not configured.');
        }

        $payload = ['contents' => [['parts' => [['text' => $prompt]]]]];
        if ($generationConfig) {
            $payload['generationConfig'] = $generationConfig;
        }

        try {
            // Key goes in a header, not in the query string, so it never ends up in logs.
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->timeout($this->timeout)
                ->retry(3, fn (int $attempt) => $attempt * 2000, function ($exception) {
                    // Retry only on rate limits / server errors / network problems.
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    return $exception instanceof RequestException
                        && ($exception->response->status() === 429 || $exception->response->serverError());
                })
                ->post(self::BASE_URL."/{$this->model}:generateContent", $payload)
                ->throw();
        } catch (RequestException $e) {
            throw new AiRequestException('Gemini API error '.$e->response->status().': '.mb_substr($e->response->body(), 0, 500), previous: $e);
        } catch (ConnectionException $e) {
            throw new AiRequestException('Gemini API is unreachable: '.$e->getMessage(), previous: $e);
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text) || $text === '') {
            $reason = $response->json('candidates.0.finishReason') ?? $response->json('promptFeedback.blockReason') ?? 'empty';
            throw new AiRequestException("Gemini returned no content (reason: {$reason}).");
        }

        return $text;
    }
}
