<?php

namespace App\Console\Commands;

use App\Services\TelegramBot;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Long-polling loop for the Telegram bot (runs as its own docker service).
 * No public URL / webhook needed — works fine on a local WSL machine.
 */
class TelegramPoll extends Command
{
    protected $signature = 'telegram:poll {--once : Process one batch and exit}';

    protected $description = 'Listen to Telegram bot commands (long polling)';

    public function handle(TelegramService $telegram, TelegramBot $bot): int
    {
        if (! $telegram->isConfigured()) {
            $this->error('TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID are not set.');

            return self::FAILURE;
        }

        $this->info('Telegram bot is listening...');

        do {
            $offset = (int) Cache::get('telegram:offset', 0);

            foreach ($telegram->getUpdates($offset) as $update) {
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
