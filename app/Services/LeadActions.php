<?php

namespace App\Services;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\LeadStatus;
use App\Jobs\ScoreLeadJob;
use App\Models\JobApplication;
use App\Models\JobLead;
use App\Services\Ai\CareerAssistant;
use App\Support\HtmlText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * What you can do with a lead — shared by the admin panel and the Telegram bot.
 */
class LeadActions
{
    public function __construct(private readonly CareerAssistant $assistant) {}

    /**
     * Lead -> application. Copies everything we know, so the funnel knows source, CV and language.
     */
    public function markApplied(JobLead $lead, bool $coverLetterSent = false): JobApplication
    {
        return DB::transaction(function () use ($lead, $coverLetterSent) {
            if ($lead->application) {
                return $lead->application;
            }

            $application = JobApplication::create([
                'job_lead_id' => $lead->id,
                'job_category_id' => $lead->job_category_id,
                'company_name' => $lead->company_name ?? 'Неизвестная компания',
                'position' => $lead->title,
                'status' => ApplicationStatus::Applied,
                'source' => $lead->source,
                'vacancy_url' => $lead->url,
                'location' => $lead->location,
                'work_mode' => $lead->work_mode,
                'language' => $lead->language,
                'german_level' => $lead->german_level,
                'job_description' => $lead->description,
                'cover_letter_sent' => $coverLetterSent || filled($lead->tailoring),
                'applied_at' => now(),
            ]);

            $lead->update(['status' => LeadStatus::Applied]);

            return $application;
        });
    }

    public function skip(JobLead $lead): void
    {
        $lead->update(['status' => LeadStatus::Skipped]);
    }

    /**
     * Cover letter + tailored bullets. Cached on the lead.
     */
    public function tailor(JobLead $lead, bool $refresh = false): string
    {
        if ($lead->tailoring && ! $refresh) {
            return $lead->tailoring;
        }

        $tailoring = $this->assistant->tailorApplication($lead, $lead->jobCategory);
        $lead->update(['tailoring' => $tailoring, 'status' => $lead->status === LeadStatus::New ? LeadStatus::Shortlisted : $lead->status]);

        return $tailoring;
    }

    /**
     * Create a lead from a pasted vacancy text or a URL (Telegram /add).
     */
    public function createFromText(string $input): JobLead
    {
        $input = trim($input);
        $url = null;

        if (preg_match('~^https?://\S+$~', $input)) {
            $url = $input;
            $input = $this->tryFetch($url) ?? $input;
        } elseif (preg_match('~https?://\S+~', $input, $m)) {
            $url = $m[0];
        }

        $parsed = $this->assistant->parseVacancy($input);
        $url = $parsed['url'] ?? $url;

        $lead = JobLead::firstOrCreate(
            ['fingerprint' => JobLead::fingerprintFor($url, $parsed['title'] ?? null, $parsed['company'] ?? null)],
            [
                'source' => ApplicationSource::guessFromEmail($url),
                'title' => mb_substr($parsed['title'] ?? 'Вакансия', 0, 255),
                'company_name' => $parsed['company'] ?? null,
                'location' => $parsed['location'] ?? null,
                'url' => $url,
                'description' => $parsed['description'] ?? null,
            ],
        );

        ScoreLeadJob::dispatchSync($lead->id);

        return $lead->fresh();
    }

    /**
     * Public career pages usually work; LinkedIn / StepStone need login or block bots — then the user pastes the text.
     */
    private function tryFetch(string $url): ?string
    {
        try {
            $response = Http::timeout(10)->withHeaders(['User-Agent' => 'Mozilla/5.0 job-tracker'])->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $text = HtmlText::fromHtml($response->body());

        return mb_strlen($text) > 500 ? $url."\n\n".$text : null;
    }
}
