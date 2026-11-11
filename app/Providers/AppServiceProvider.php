<?php

namespace App\Providers;

use App\Contracts\AiClient;
use App\Services\Ai\GeminiClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Swap the provider here (OpenAI, Claude, local model) — the rest of the app only knows AiClient.
        $this->app->singleton(AiClient::class, fn () => new GeminiClient(
            apiKey: config('services.gemini.key'),
            model: config('services.gemini.model'),
            timeout: (int) config('services.gemini.timeout', 60),
        ));
    }

    public function boot(): void
    {
        //
    }
}
