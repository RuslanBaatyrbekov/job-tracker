<?php

namespace Tests\Feature;

use App\Exceptions\AiRequestException;
use App\Services\Ai\GeminiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class GeminiClientTest extends TestCase
{
    private function client(): GeminiClient
    {
        return new GeminiClient('secret-key', 'gemini-test');
    }

    public function test_it_decodes_json_and_sends_key_in_header(): void
    {
        $this->fakeGemini("```json\n{\"relevant_ids\": [1, 3]}\n```");

        $this->assertSame(['relevant_ids' => [1, 3]], $this->client()->json('prompt'));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'models/gemini-test:generateContent')
            && ! str_contains($r->url(), 'secret-key')
            && $r->header('x-goog-api-key')[0] === 'secret-key');
    }

    public function test_server_error_throws_instead_of_returning_empty_result(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('overloaded', 503)]);

        $this->expectException(AiRequestException::class);

        // Retries sleep between attempts; keep the test fast.
        Sleep::fake();
        $this->client()->json('prompt');
    }

    public function test_client_error_is_not_retried(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('bad key', 400)]);

        try {
            $this->client()->text('prompt');
            $this->fail('Exception expected');
        } catch (AiRequestException) {
            Http::assertSentCount(1);
        }
    }

    public function test_invalid_json_throws(): void
    {
        $this->fakeGemini('not json at all');

        $this->expectException(AiRequestException::class);
        $this->client()->json('prompt');
    }
}
