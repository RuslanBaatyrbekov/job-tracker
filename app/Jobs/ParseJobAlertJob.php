<?php

namespace App\Jobs;

use App\Enums\ApplicationSource;
use App\Enums\EmailStatus;
use App\Models\Email;
use App\Models\JobLead;
use App\Services\Ai\CareerAssistant;
use App\Services\ImapService;
use App\Support\HtmlText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Job alert email (LinkedIn / StepStone / Indeed / ...) -> list of leads -> scoring.
 */
class ParseJobAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    /** @var int[] */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $emailId) {}

    public function handle(ImapService $imap, CareerAssistant $assistant): void
    {
        $email = Email::find($this->emailId);

        if (! $email || $email->status !== EmailStatus::PendingAlertParse) {
            return;
        }

        if (empty($email->body_html) && empty($email->body_text) && ! $imap->fetchBodyForEmail($email)) {
            throw new RuntimeException("Could not download body of job alert {$email->id}");
        }

        // HTML with links kept is much better here than the text part: we need the job URLs.
        $body = $email->body_html ? HtmlText::fromHtml($email->body_html) : (string) $email->body_text;

        $jobs = $assistant->extractJobsFromAlert((string) $email->subject, (string) $email->from_address, $body);
        $source = ApplicationSource::guessFromEmail($email->from_address);
        $created = 0;

        foreach ($jobs as $job) {
            $fingerprint = JobLead::fingerprintFor($job['url'] ?? null, $job['title'] ?? null, $job['company'] ?? null);

            $lead = JobLead::firstOrCreate(['fingerprint' => $fingerprint], [
                'source_email_id' => $email->id,
                'source' => $source,
                'title' => mb_substr($job['title'], 0, 255),
                'company_name' => isset($job['company']) ? mb_substr($job['company'], 0, 255) : null,
                'location' => isset($job['location']) ? mb_substr($job['location'], 0, 255) : null,
                'url' => $job['url'] ?? null,
            ]);

            if ($lead->wasRecentlyCreated) {
                $created++;
                ScoreLeadJob::dispatch($lead->id);
            }
        }

        $email->update(['status' => EmailStatus::Processed]);
        Log::info("Job alert {$email->id}: ".count($jobs)." jobs found, {$created} new leads");
    }

    public function failed(Throwable $e): void
    {
        Email::whereKey($this->emailId)->update(['status' => EmailStatus::Failed]);
        Log::error("Job alert {$this->emailId} parsing failed: ".$e->getMessage());
    }
}
