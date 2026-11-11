<?php

namespace Tests\Feature;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Jobs\GenerateInterviewPrepJob;
use App\Models\JobApplication;
use App\Models\JobCategory;
use App\Services\FunnelStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_interview_is_counted_even_after_later_rejection(): void
    {
        $app = JobApplication::create(['company_name' => 'A', 'position' => 'Dev', 'status' => ApplicationStatus::Applied, 'source' => ApplicationSource::LinkedIn, 'applied_at' => now()]);
        $app->update(['status' => ApplicationStatus::Interview]);
        $app->update(['status' => ApplicationStatus::Rejected]);
        JobApplication::create(['company_name' => 'B', 'position' => 'Dev', 'status' => ApplicationStatus::Applied, 'applied_at' => now()]);

        $stats = app(FunnelStats::class)->summary();

        $this->assertSame(2, $stats['applications']);
        $this->assertSame(1, $stats['interviews']);
        $this->assertSame(50.0, $stats['interview_rate']);

        $bySource = app(FunnelStats::class)->bySource();
        $this->assertSame(1, $bySource['LinkedIn']['interviews']);
        $this->assertSame(0, $bySource['Не указан']['interviews']);
    }

    public function test_strategy_report_uses_funnel_numbers(): void
    {
        $category = JobCategory::create(['name' => 'Laravel', 'resume_text' => 'My resume']);
        JobApplication::create(['company_name' => 'A', 'position' => 'Dev', 'status' => ApplicationStatus::Applied, 'job_category_id' => $category->id, 'applied_at' => now()]);
        $this->fakeGemini('**Bottleneck:** volume');

        $this->artisan('ai:strategy')->assertSuccessful();

        $this->assertSame('**Bottleneck:** volume', $category->fresh()->ai_strategy_report);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'generativelanguage')
            && str_contains($r['contents'][0]['parts'][0]['text'], '"response_rate"'));
    }

    public function test_interview_prep_is_generated_once_and_sent(): void
    {
        $app = JobApplication::create(['company_name' => 'Acme', 'position' => 'Dev', 'status' => ApplicationStatus::Interview, 'applied_at' => now()]);
        $this->fakeGemini("## Technical questions\n- **Queues**: explain retries");

        GenerateInterviewPrepJob::dispatchSync($app->id);
        GenerateInterviewPrepJob::dispatchSync($app->id);

        $this->assertSame(1, $app->notes()->where('type', 'interview_prep')->count());
        $this->assertStringContainsString('<b>Queues</b>', $this->telegramMessages()[0]);
    }
}
