<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\NoteType;
use App\Jobs\GenerateInterviewPrepJob;
use App\Models\Email;
use App\Models\JobApplication;
use App\Models\JobCategory;
use App\Services\Ai\CareerAssistant;
use App\Support\CompanyName;
use Illuminate\Support\Facades\DB;

/**
 * Turns the AI analysis of an employer email into application state.
 *
 * The important part: an email about an EXISTING application (rejection after
 * "we received your application") updates it instead of creating a duplicate,
 * otherwise the funnel numbers are meaningless.
 */
class ApplicationRecorder
{
    /**
     * @return array{application: JobApplication, created: bool, status_changed: bool}
     */
    public function record(Email $email, array $analysis): array
    {
        $status = CareerAssistant::normalizeStatus($analysis['status'] ?? null);
        $company = $this->clean($analysis['company_name'] ?? null);
        $position = $this->clean($analysis['position'] ?? null);
        $domain = CompanyName::domainFromEmail($email->from_address);
        $categoryId = $this->categoryId($analysis['category_name'] ?? null);

        return DB::transaction(function () use ($email, $analysis, $status, $company, $position, $domain, $categoryId) {
            $application = $this->findExisting($company, $position, $domain, $status);
            $created = false;
            $statusChanged = false;

            if (! $application) {
                $application = new JobApplication([
                    'source_email_id' => $email->id,
                    'company_name' => $company ?? 'Неизвестная компания',
                    'position' => $position ?? 'Разработчик',
                    'status' => $status,
                    'applied_at' => $email->date,
                ]);
                $application->pendingStatusEmailId = $email->id;
                $created = true;
                $statusChanged = true;
            } elseif ($application->status->canTransitionTo($status)) {
                $application->status = $status;
                $application->pendingStatusEmailId = $email->id;
                $statusChanged = true;
            }

            // Fill gaps, never overwrite what is already known (could be edited by hand).
            if ($position && in_array($application->position, [null, '', 'Разработчик'], true)) {
                $application->position = $position;
            }
            if ($company && $application->company_name === 'Неизвестная компания') {
                $application->company_name = $company;
            }
            $application->company_domain ??= $domain;
            $application->job_category_id ??= $categoryId;
            $application->last_activity_at = $email->date ?? now();
            $application->ai_metadata = array_merge($application->ai_metadata ?? [], [
                'last_analysis' => $analysis,
            ]);
            $application->save();

            if (! empty($analysis['rejection_reason'])) {
                $application->notes()->create([
                    'type' => NoteType::RejectionReason,
                    'content' => $analysis['rejection_reason'],
                ]);
            }

            if (! empty($analysis['ai_advice'])) {
                $application->notes()->create([
                    'type' => NoteType::AiAdvice,
                    'content' => $analysis['ai_advice'],
                ]);
            }

            if ($statusChanged && $status === ApplicationStatus::Interview) {
                GenerateInterviewPrepJob::dispatch($application->id, $email->id)->afterCommit();
            }

            return ['application' => $application, 'created' => $created, 'status_changed' => $statusChanged];
        });
    }

    /**
     * Find the application this email most likely belongs to.
     */
    public function findExisting(?string $company, ?string $position, ?string $domain, ApplicationStatus $incoming): ?JobApplication
    {
        $key = CompanyName::key($company);
        if (! $key && ! $domain) {
            return null;
        }

        $candidates = JobApplication::query()
            ->where(function ($q) use ($key, $domain) {
                if ($key) {
                    $q->orWhere('company_key', $key);
                }
                if ($domain) {
                    $q->orWhere('company_domain', $domain);
                }
            })
            ->latest('applied_at')
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $scored = $candidates->map(function (JobApplication $app) use ($position) {
            $similarity = $position ? CompanyName::titleSimilarity($app->position, $position) : 50;

            return ['app' => $app, 'similarity' => $similarity, 'open' => ! $app->status->isFinal()];
        });

        // Prefer open processes, then the closest job title.
        $best = $scored->sortByDesc(fn ($c) => ($c['open'] ? 1000 : 0) + $c['similarity'])->first();

        // A brand-new "applied" confirmation for a clearly different role = a new application.
        if ($incoming === ApplicationStatus::Applied && $position && $best['similarity'] < 45) {
            return null;
        }

        // A new confirmation after the previous process was closed = applied again.
        if ($incoming === ApplicationStatus::Applied && ! $best['open']) {
            return null;
        }

        return $best['app'];
    }

    private function categoryId(?string $name): ?int
    {
        if (! $name) {
            return null;
        }

        return JobCategory::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))])
            ->value('id');
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || strtolower($value) === 'null' ? null : $value;
    }
}
