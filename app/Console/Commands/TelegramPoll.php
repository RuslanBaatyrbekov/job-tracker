<?php

namespace App\Console\Commands;

use App\Services\TelegramBot;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Long-polling loop for the Telegram bot (runs as its own docker service).
 * No public URL / webhook needed — works fine on a local WSL machine.
 *
 * Network problems (laptop asleep, WSL DNS gone, TLS resets) are expected here:
 * back off exponentially and log only the start of an outage and the recovery,
 * instead of hundreds of identical lines per second.
 */
class TelegramPoll extends Command
{
    protected $signature = 'telegram:poll {--once : Process one batch and exit}';

    protected $description = 'Listen to Telegram bot commands (long polling)';

    private const MAX_BACKOFF_SECONDS = 60;

    /** Short blips (a single TLS reset) are normal — only report outages longer than this. */
    private const REPORT_AFTER_FAILURES = 3;

    public function handle(TelegramService $telegram, TelegramBot $bot): int
    {
        if (! $telegram->isConfigured()) {
            $this->error('TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID are not set.');

            return self::FAILURE;
        }

        $this->info('Telegram bot is listening...');
        $failures = 0;

        do {
            $offset = (int) Cache::get('telegram:offset', 0);
            $updates = $telegram->getUpdates($offset);

            if ($updates === null) {
                $failures++;

                if ($failures === self::REPORT_AFTER_FAILURES) {
                    Log::warning("Telegram unreachable ({$failures} attempts in a row), backing off: {$telegram->lastError}");
                }

                Sleep::for(min(2 ** min($failures, 6), self::MAX_BACKOFF_SECONDS))->seconds();

                continue;
            }

            if ($failures >= self::REPORT_AFTER_FAILURES) {
                Log::info("Telegram connection restored after {$failures} failed attempts.");
            }
            $failures = 0;

            foreach ($updates as $update) {
                // Move the offset first: a failing update must not be re-processed forever.
                Cache::forever('telegram:offset', $update['update_id'] + 1);

                try {
                    $bot->handle($update);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }
}
