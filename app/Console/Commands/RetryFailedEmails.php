<?php

namespace App\Console\Commands;

use App\Enums\EmailStatus;
use App\Jobs\FilterEmailsViaAiJob;
use App\Models\Email;
use Illuminate\Console\Command;

class RetryFailedEmails extends Command
{
    protected $signature = 'mail:retry-failed';

    protected $description = 'Send emails that failed AI analysis through the pipeline again';

    public function handle(): int
    {
        $count = Email::where('status', EmailStatus::Failed)
            ->update(['status' => EmailStatus::PendingSubjectScan]);

        if ($count > 0) {
            FilterEmailsViaAiJob::dispatch();
        }

        $this->info("Re-queued {$count} emails.");

        return self::SUCCESS;
    }
}
