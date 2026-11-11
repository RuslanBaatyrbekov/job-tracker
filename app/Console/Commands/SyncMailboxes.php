<?php

namespace App\Console\Commands;

use App\Jobs\FilterEmailsViaAiJob;
use App\Models\Mailbox;
use App\Services\ImapService;
use Illuminate\Console\Command;

class SyncMailboxes extends Command
{
    protected $signature = 'mail:sync-headers {--limit=50 : How many latest emails to scan}';

    protected $description = 'Download email headers from all active mailboxes and start AI filtering';

    public function handle(ImapService $imapService): int
    {
        ini_set('memory_limit', '-1');

        $limit = (int) $this->option('limit');

        foreach (Mailbox::where('is_active', true)->get() as $mailbox) {
            $this->info("Syncing {$mailbox->email} (limit {$limit})...");
            $count = $imapService->syncHeaders($mailbox, $limit);
            $this->info("  new headers: {$count}");
        }

        FilterEmailsViaAiJob::dispatch();

        return self::SUCCESS;
    }
}
