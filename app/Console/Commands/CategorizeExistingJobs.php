<?php

namespace App\Console\Commands;

use App\Exceptions\AiRequestException;
use App\Models\JobApplication;
use App\Models\JobCategory;
use App\Services\Ai\CareerAssistant;
use Illuminate\Console\Command;

class CategorizeExistingJobs extends Command
{
    protected $signature = 'jobs:categorize-existing {--sleep=4 : Pause between requests, seconds (free tier ~15 RPM)}';

    protected $description = 'Assign a resume category to applications without one using AI';

    public function handle(CareerAssistant $assistant): int
    {
        $categories = JobCategory::all();
        if ($categories->isEmpty()) {
            $this->error('No categories yet. Create them in /admin/job-categories first.');

            return self::FAILURE;
        }

        $applications = JobApplication::whereNull('job_category_id')->get();
        if ($applications->isEmpty()) {
            $this->info('All applications already have a category.');

            return self::SUCCESS;
        }

        $names = $categories->map(fn ($c) => trim($c->name))->all();
        $this->info("Applications to categorize: {$applications->count()}. Categories: ".implode(', ', $names));

        foreach ($applications as $application) {
            try {
                $answer = trim((string) $assistant->categorize($application->position, $application->company_name, $names));
            } catch (AiRequestException $e) {
                $this->error("  AI error: {$e->getMessage()}");

                continue;
            }

            $category = $categories->first(fn ($c) => mb_strtolower(trim($c->name)) === mb_strtolower($answer));

            if ($category) {
                $application->update(['job_category_id' => $category->id]);
                $this->line("  [+] {$application->company_name} / {$application->position} -> {$category->name}");
            } else {
                $this->warn("  [-] {$application->company_name}: AI returned unknown category '{$answer}'");
            }

            sleep((int) $this->option('sleep'));
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
