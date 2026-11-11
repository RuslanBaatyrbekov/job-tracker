<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.key' => 'test-key',
            'services.telegram.token' => 'test-token',
            'services.telegram.chat_id' => '42',
        ]);

        // No real HTTP calls in tests, ever.
        Http::preventStrayRequests();
    }

    /**
     * Fake Gemini answers in order. Arrays are returned as JSON text, strings as-is.
     */
    protected function fakeGemini(array|string ...$answers): void
    {
        $sequence = Http::sequence();
        foreach ($answers as $answer) {
            $text = is_array($answer) ? json_encode($answer) : $answer;
            $sequence->push(['candidates' => [['content' => ['parts' => [['text' => $text]]]]]]);
        }

        Http::fake([
            'generativelanguage.googleapis.com/*' => $sequence,
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []]),
        ]);
    }

    protected function fakeTelegramOnly(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
    }

    /** @return array<int, string> texts sent to Telegram */
    protected function telegramMessages(): array
    {
        return Http::recorded()
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'api.telegram.org') && str_contains($pair[0]->url(), 'sendMessage'))
            ->map(fn ($pair) => $pair[0]['text'])
            ->values()
            ->all();
    }
}
