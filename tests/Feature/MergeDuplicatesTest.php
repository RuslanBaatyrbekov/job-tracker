<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\JobApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MergeDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_duplicates_are_merged_into_the_first_application(): void
    {
        $first = JobApplication::create(['company_name' => 'Acme GmbH', 'position' => 'PHP Developer (m/w/d)', 'status' => ApplicationStatus::Applied, 'applied_at' => now()->subDays(10)]);
        $second = JobApplication::create(['company_name' => 'ACME', 'position' => 'PHP Developer', 'status' => ApplicationStatus::Rejected, 'applied_at' => now()->subDays(2)]);
        $second->notes()->create(['type' => 'rejection_reason', 'content' => 'German']);
        $other = JobApplication::create(['company_name' => 'Acme', 'position' => 'Data Analyst', 'status' => ApplicationStatus::Applied, 'applied_at' => now()]);

        $this->artisan('applications:merge-duplicates')->assertSuccessful();

        $this->assertModelMissing($second);
        $this->assertModelExists($other);
        $this->assertSame(ApplicationStatus::Rejected, $first->fresh()->status);
        $this->assertSame(1, $first->notes()->count());
    }
}
