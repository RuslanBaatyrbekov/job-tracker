<?php

namespace App\Console\Commands;

use App\Models\JobApplication;
use App\Support\CompanyName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off cleanup: before deduplication every email created its own application,
 * so "applied" + "rejected" from one company were two rows. Merge them.
 */
class MergeDuplicateApplications extends Command
{
    protected $signature = 'applications:merge-duplicates {--dry-run : Only show what would be merged}';

    protected $description = 'Merge applications that belong to the same company and role';

    public function handle(): int
    {
        // Make sure keys exist for old rows.
        JobApplication::whereNull('company_key')->each(fn (JobApplication $a) => $a->updateQuietly([
            'company_key' => CompanyName::key($a->company_name),
        ]));

        $groups = JobApplication::whereNotNull('company_key')
            ->orderBy('applied_at')
            ->get()
            ->groupBy('company_key')
            ->filter(fn ($group) => $group->count() > 1);

        $merged = 0;

        foreach ($groups as $key => $applications) {
            // Inside one company: same role (similar title) = same process.
            $clusters = [];
            foreach ($applications as $application) {
                $placed = false;
                foreach ($clusters as &$cluster) {
                    if (CompanyName::titleSimilarity($cluster[0]->position, $application->position) >= 60) {
                        $cluster[] = $application;
                        $placed = true;
                        break;
                    }
                }
                unset($cluster);
                if (! $placed) {
                    $clusters[] = [$application];
                }
            }

            foreach ($clusters as $cluster) {
                if (count($cluster) < 2) {
                    continue;
                }

                $primary = $cluster[0]; // the earliest = real application date
                $status = $primary->status;
                foreach (array_slice($cluster, 1) as $duplicate) {
                    if ($status->canTransitionTo($duplicate->status)) {
                        $status = $duplicate->status;
                    }
                }

                $this->line(sprintf(
                    '%s / %s: %d rows -> #%d (%s)',
                    $primary->company_name, $primary->position, count($cluster), $primary->id, $status->value,
                ));

                if ($this->option('dry-run')) {
                    continue;
                }

                DB::transaction(function () use ($primary, $cluster, $status) {
                    foreach (array_slice($cluster, 1) as $duplicate) {
                        $duplicate->notes()->update(['job_application_id' => $primary->id]);
                        $duplicate->statusChanges()->update(['job_application_id' => $primary->id]);
                        $primary->job_category_id ??= $duplicate->job_category_id;
                        $primary->company_domain ??= $duplicate->company_domain;
                        $primary->last_activity_at = max($primary->last_activity_at, $duplicate->last_activity_at ?? $duplicate->applied_at);
                        $duplicate->delete();
                    }

                    $primary->status = $status;
                    $primary->save();
                });

                $merged += count($cluster) - 1;
            }
        }

        $this->info($this->option('dry-run') ? 'Dry run finished.' : "Merged {$merged} duplicate rows.");

        return self::SUCCESS;
    }
}
