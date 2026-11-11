<?php

namespace App\Console\Commands;

use App\Enums\NoteType;
use App\Exceptions\AiRequestException;
use App\Models\ApplicationNote;
use App\Models\JobCategory;
use App\Services\Ai\CareerAssistant;
use App\Services\FunnelStats;
use Illuminate\Console\Command;

class GenerateAiStrategy extends Command
{
    protected $signature = 'ai:strategy {--force : Rebuild even if nothing changed}';

    protected $description = 'Data-driven strategy review per resume category (funnel numbers + new rejection reasons)';

    public function handle(CareerAssistant $assistant, FunnelStats $stats): int
    {
        $categories = JobCategory::whereNotNull('resume_text')->get();

        if ($categories->isEmpty()) {
            $this->warn('No categories with a resume yet.');

            return self::SUCCESS;
        }

        foreach ($categories as $category) {
            $this->info("Category: {$category->name}");

            $rejections = ApplicationNote::with('jobApplication')
                ->where('type', NoteType::RejectionReason)
                ->whereHas('jobApplication', fn ($q) => $q->where('job_category_id', $category->id))
                ->when($category->last_analyzed_at, fn ($q) => $q->where('created_at', '>', $category->last_analyzed_at))
                ->latest()
                ->take(20)
                ->get();

            $newApplications = $category->jobApplications()
                ->when($category->last_analyzed_at, fn ($q) => $q->where('updated_at', '>', $category->last_analyzed_at))
                ->exists();
            $resumeUpdated = $category->last_analyzed_at && $category->updated_at > $category->last_analyzed_at;

            // Nothing happened since the last review — save the API quota.
            if (! $this->option('force') && $category->last_analyzed_at && $rejections->isEmpty() && ! $resumeUpdated && ! $newApplications) {
                $this->line('  [-] nothing changed, skipped');

                continue;
            }

            $numbers = [
                'all_time' => $stats->summary($category->id),
                'last_14_days' => $stats->summary($category->id, now()->subDays(14)),
                'by_source' => $stats->bySource($category->id),
            ];

            try {
                $report = $assistant->strategyReport(
                    $category,
                    $numbers,
                    $rejections->map(fn ($r) => "- {$r->jobApplication->position}: {$r->content}"),
                    $category->ai_strategy_report,
                );
            } catch (AiRequestException $e) {
                $this->error("  AI error: {$e->getMessage()}");

                continue;
            }

            // Keep updated_at untouched: it tells us when the resume itself was edited.
            $category->timestamps = false;
            $category->update(['ai_strategy_report' => $report, 'last_analyzed_at' => now()]);
            $category->timestamps = true;

            $this->line('  [+] report updated');
            sleep(2);
        }

        return self::SUCCESS;
    }
}
