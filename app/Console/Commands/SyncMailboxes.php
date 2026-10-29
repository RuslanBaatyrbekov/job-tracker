<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Mailbox;
use App\Services\ImapService;

class SyncMailboxes extends Command
{
    protected $signature = 'mail:sync-headers';
    protected $description = 'Синхронизирует заголовки писем со всех активных ящиков';

    public function handle(ImapService $imapService)
    {
        $this->info('Начинаем синхронизацию ящиков...');

        $mailboxes = Mailbox::where('is_active', true)->get();

        foreach ($mailboxes as $mailbox) {
            $this->info("Синхронизация {$mailbox->email}...");
            $count = $imapService->syncHeaders($mailbox);
            $this->info("Добавлено {$count} новых заголовков писем.");
        }

        $this->info('Синхронизация завершена!');

        \App\Jobs\FilterEmailsViaAiJob::dispatch();
    }
}
