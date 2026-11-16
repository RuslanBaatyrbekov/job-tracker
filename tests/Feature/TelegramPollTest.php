<?php

namespace Tests\Feature;

use App\Services\TelegramService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class TelegramPollTest extends TestCase
{
    public function test_backs_off_and_does_not_spam_logs_when_telegram_is_unreachable(): void
    {
        Sleep::fake();
        Log::spy();
        Http::fake(['api.telegram.org/*' => fn () => throw new ConnectionException('Could not resolve host: api.telegram.org')]);

        // --once = one iteration; run several to simulate an outage.
        foreach (range(1, 5) as $i) {
            $this->artisan('telegram:poll', ['--once' => true])->assertSuccessful();
        }

        // Each failed attempt sleeps (before the fix it retried instantly — hundreds of requests per second).
        Sleep::assertSleptTimes(5);
    }

    public function test_failed_request_returns_null_with_redacted_reason(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response('Bad Gateway', 502)]);

        $telegram = app(TelegramService::class);

        $this->assertNull($telegram->getUpdates(0));
        $this->assertStringContainsString('HTTP 502', $telegram->lastError);
        $this->assertStringNotContainsString('test-token', $telegram->lastError);
    }
}
