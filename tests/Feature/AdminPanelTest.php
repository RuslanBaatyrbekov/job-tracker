<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Filament\Resources\JobLeadResource\Pages\ListJobLeads;
use App\Filament\Widgets\ActivityChart;
use App\Filament\Widgets\AiInsightsWidget;
use App\Filament\Widgets\ApplicationStatsWidget;
use App\Filament\Widgets\ApplicationStatusChart;
use App\Filament\Widgets\OverallFunnelChart;
use App\Filament\Widgets\SourceConversionWidget;
use App\Models\JobApplication;
use App\Models\JobCategory;
use App\Models\JobLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_render(): void
    {
        $this->actingAs(User::factory()->create());
        $category = JobCategory::create(['name' => 'Laravel', 'resume_text' => 'cv', 'ai_strategy_report' => '**Do more**']);
        $application = JobApplication::create(['company_name' => 'Acme', 'position' => 'Dev', 'status' => ApplicationStatus::Interview, 'job_category_id' => $category->id, 'applied_at' => now()]);
        $application->notes()->create(['type' => 'ai_advice', 'content' => 'Prepare']);
        $lead = JobLead::create(['title' => 'Laravel Dev', 'score' => 80, 'red_flags' => ['x']]);

        foreach ([
            '/admin',
            '/admin/job-applications',
            "/admin/job-applications/{$application->id}/edit",
            '/admin/job-leads',
            "/admin/job-leads/{$lead->id}/edit",
            '/admin/job-categories',
            '/admin/emails',
            '/admin/mailboxes',
        ] as $url) {
            $this->get($url)->assertOk();
        }

        // Dashboard widgets are lazy-loaded, render them explicitly.
        foreach ([
            ApplicationStatsWidget::class,
            AiInsightsWidget::class,
            ActivityChart::class,
            OverallFunnelChart::class,
            ApplicationStatusChart::class,
            SourceConversionWidget::class,
        ] as $widget) {
            Livewire::test($widget)->assertOk();
        }

        Livewire::test(ListJobLeads::class)
            ->assertCanSeeTableRecords([$lead]);
    }
}
