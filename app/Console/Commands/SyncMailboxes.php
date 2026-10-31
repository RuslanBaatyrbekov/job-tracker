<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Mailbox;
use App\Services\ImapService;

class SyncMailboxes extends Command
{
    protected $signature = 'mail:sync-headers {--limit=50 : Количество последних писем для сканирования}';
    protected $description = 'Синхронизирует заголовки писем со всех активных ящиков';

    public function handle(ImapService $imapService)
    {
        ini_set('memory_limit', '-1');
        $this->info('Начинаем синхронизацию ящиков...');

        $limit = (int) $this->option('limit');
        $mailboxes = Mailbox::where('is_active', true)->get();

        foreach ($mailboxes as $mailbox) {
            $this->info("Синхронизация {$mailbox->email} с лимитом: {$limit}...");
            $count = $imapService->syncHeaders($mailbox, $limit);
            $this->info("Добавлено {$count} новых заголовков писем.");
        }

        $this->info('Синхронизация завершена!');

        \App\Jobs\FilterEmailsViaAiJob::dispatch();
    }
}
