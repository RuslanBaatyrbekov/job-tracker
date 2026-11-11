<?php

namespace App\Jobs;

use App\Enums\EmailStatus;
use App\Models\Email;
use App\Services\Ai\CareerAssistant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Stage 1 of the pipeline: classify new emails by subject + sender.
 *
 * If the AI call fails the exception bubbles up and the queue retries the job.
 * Emails stay in "pending_subject_scan" — nothing is marked irrelevant by mistake.
 */
class FilterEmailsViaAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const BATCH_SIZE = 50;

    public int $tries = 4;

    /** @var int[] */
    public array $backoff = [60, 300, 900];

    public function middleware(): array
    {
        return [(new WithoutOverlapping('filter-emails'))->releaseAfter(60)->expireAfter(600)];
    }

    public function handle(CareerAssistant $assistant): void
    {
        $pendingEmails = Email::where('status', EmailStatus::PendingSubjectScan)
            ->orderBy('id')
            ->limit(self::BATCH_SIZE)
            ->get();

        if ($pendingEmails->isEmpty()) {
            return;
        }

        $result = $assistant->classifyEmails($pendingEmails->map(fn (Email $email) => [
            'id' => $email->id,
            'subject' => $email->subject,
            'from_name' => $email->from_name,
            'from_address' => $email->from_address,
        ])->values()->all());

        foreach ($pendingEmails as $email) {
            if (in_array($email->id, $result['relevant_ids'], true)) {
                $email->update(['status' => EmailStatus::PendingBodyAnalysis]);
                AnalyzeEmailBodyJob::dispatch($email->id);
            } elseif (in_array($email->id, $result['job_alert_ids'], true)) {
                $email->update(['status' => EmailStatus::PendingAlertParse]);
                ParseJobAlertJob::dispatch($email->id);
            } else {
                $email->update(['status' => EmailStatus::Irrelevant]);
            }
        }

        Log::info('Email filter: '.count($result['relevant_ids']).' relevant, '.count($result['job_alert_ids']).' job alerts of '.$pendingEmails->count());

        // More emails waiting (e.g. after a manual sync with a big limit) — continue with the next batch.
        if ($pendingEmails->count() === self::BATCH_SIZE) {
            self::dispatch();
        }
    }
}
