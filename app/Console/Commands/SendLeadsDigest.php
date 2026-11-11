<?php

namespace App\Console\Commands;

use App\Enums\LeadStatus;
use App\Models\JobLead;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class SendLeadsDigest extends Command
{
    protected $signature = 'leads:digest';

    protected $description = 'Send the best new vacancies to Telegram';

    public function handle(TelegramService $telegram): int
    {
        $leads = JobLead::where('status', LeadStatus::New)
            ->whereNull('notified_at')
            ->where('score', '>=', config('job-search.leads.digest_min_score'))
            ->orderByDesc('score')
            ->limit(config('job-search.leads.digest_limit'))
            ->get();

        if ($leads->isEmpty()) {
            $this->info('No new leads above the threshold.');

            return self::SUCCESS;
        }

        $telegram->send('☀️ <b>Топ вакансий на сегодня: '.$leads->count().'</b>');

        foreach ($leads as $lead) {
            $telegram->sendLeadCard($lead);
            $lead->update(['notified_at' => now()]);
        }

        // Leads below the threshold are not interesting — don't keep them "new" forever.
        JobLead::where('status', LeadStatus::New)
            ->whereNotNull('scored_at')
            ->where('score', '<', config('job-search.leads.digest_min_score'))
            ->where('created_at', '<', now()->subDays(7))
            ->update(['status' => LeadStatus::Skipped]);

        $this->info("Sent {$leads->count()} leads.");

        return self::SUCCESS;
    }
}
