<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\JobApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowUpsTest extends TestCase
{
    use RefreshDatabase;

    private function application(int $daysAgo, array $attributes = []): JobApplication
    {
        return JobApplication::create(array_merge([
            'company_name' => 'Acme',
            'position' => 'PHP Developer',
            'status' => ApplicationStatus::Applied,
            'applied_at' => now()->subDays($daysAgo),
            'last_activity_at' => now()->subDays($daysAgo),
        ], $attributes));
    }

    public function test_reminds_once_and_marks_ghosted(): void
    {
        $this->fakeGemini("Subject: Following up\n\nHello [Name], ...");

        $fresh = $this->application(3);
        $stale = $this->application(12);
        $ghosted = $this->application(40);

        $this->artisan('applications:follow-ups')->assertSuccessful();

        $this->assertNull($fresh->fresh()->follow_up_reminded_at);
        $this->assertNotNull($stale->fresh()->follow_up_reminded_at);
        $this->assertSame(1, $stale->notes()->where('type', 'follow_up_draft')->count());
        $this->assertSame(ApplicationStatus::NoResponse, $ghosted->fresh()->status);

        // Next day: no second reminder for the same application.
        $this->artisan('applications:follow-ups')->assertSuccessful();
        $this->assertSame(1, $stale->notes()->where('type', 'follow_up_draft')->count());
    }
}
