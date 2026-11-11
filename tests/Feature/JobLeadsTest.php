<?php

namespace Tests\Feature;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\EmailStatus;
use App\Enums\LeadStatus;
use App\Jobs\ParseJobAlertJob;
use App\Jobs\ScoreLeadJob;
use App\Models\Email;
use App\Models\JobCategory;
use App\Models\JobLead;
use App\Models\Mailbox;
use App\Services\LeadActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class JobLeadsTest extends TestCase
{
    use RefreshDatabase;

    private function alertEmail(): Email
    {
        $mailbox = Mailbox::create(['email' => 'me@gmail.com', 'host' => 'imap.gmail.com', 'username' => 'me', 'password' => 'x']);

        return Email::create([
            'mailbox_id' => $mailbox->id,
            'imap_uid' => '1',
            'subject' => 'PHP Developer: 3 new jobs',
            'from_address' => 'jobalerts-noreply@linkedin.com',
            'date' => now(),
            'status' => EmailStatus::PendingAlertParse,
            'body_html' => '<a href="https://www.linkedin.com/jobs/view/1/?trk=x">Laravel Developer</a> Acme GmbH · Frankfurt',
        ]);
    }

    public function test_job_alert_becomes_leads_without_duplicates(): void
    {
        Queue::fake([ScoreLeadJob::class]);
        JobLead::create(['title' => 'Old', 'url' => 'https://www.linkedin.com/jobs/view/1?trk=old']);

        $this->fakeGemini(['jobs' => [
            ['title' => 'Laravel Developer', 'company' => 'Acme GmbH', 'location' => 'Frankfurt', 'url' => 'https://www.linkedin.com/jobs/view/1/?trk=x'],
            ['title' => 'PHP Backend Engineer', 'company' => 'Beta AG', 'location' => 'Remote', 'url' => 'https://www.linkedin.com/jobs/view/2/'],
            ['title' => '', 'company' => 'broken row'],
        ]]);

        $email = $this->alertEmail();
        ParseJobAlertJob::dispatchSync($email->id);

        $this->assertSame(2, JobLead::count()); // job 1 already existed
        $new = JobLead::where('title', 'PHP Backend Engineer')->sole();
        $this->assertSame(ApplicationSource::LinkedIn, $new->source);
        $this->assertSame(EmailStatus::Processed, $email->fresh()->status);
        Queue::assertPushed(ScoreLeadJob::class, 1);

        // The AI saw the link next to the job title.
        Http::assertSent(fn ($r) => str_contains($r['contents'][0]['parts'][0]['text'], 'Laravel Developer (https://www.linkedin.com/jobs/view/1/?trk=x)'));
    }

    public function test_lead_is_scored(): void
    {
        $category = JobCategory::create(['name' => 'Laravel Backend']);
        $lead = JobLead::create(['title' => 'Laravel Developer', 'company_name' => 'Acme']);

        $this->fakeGemini([
            'score' => 87, 'reason' => 'Laravel + hybrid Frankfurt', 'red_flags' => [],
            'category_name' => 'laravel backend', 'work_mode' => 'hybrid', 'german_level' => 'B1', 'language' => 'en',
        ]);

        ScoreLeadJob::dispatchSync($lead->id);

        $lead->refresh();
        $this->assertSame(87, $lead->score);
        $this->assertSame('Laravel + hybrid Frankfurt', $lead->score_reason);
        $this->assertSame($category->id, $lead->job_category_id);
        $this->assertSame('hybrid', $lead->work_mode);
    }

    public function test_marking_lead_as_applied_creates_application_with_source(): void
    {
        $this->fakeTelegramOnly();
        $lead = JobLead::create([
            'title' => 'Laravel Developer', 'company_name' => 'Acme GmbH', 'source' => ApplicationSource::StepStone,
            'url' => 'https://www.stepstone.de/job/1', 'language' => 'de', 'tailoring' => '## Cover letter',
        ]);

        $application = app(LeadActions::class)->markApplied($lead);

        $this->assertSame(ApplicationStatus::Applied, $application->status);
        $this->assertSame(ApplicationSource::StepStone, $application->source);
        $this->assertTrue($application->cover_letter_sent);
        $this->assertSame('acme', $application->company_key);
        $this->assertSame(LeadStatus::Applied, $lead->fresh()->status);

        // Idempotent: second click does not create a second application.
        $this->assertSame($application->id, app(LeadActions::class)->markApplied($lead->fresh())->id);
    }

    public function test_digest_sends_only_good_new_leads_once(): void
    {
        $this->fakeTelegramOnly();
        JobLead::create(['title' => 'Great', 'score' => 90]);
        JobLead::create(['title' => 'Weak', 'score' => 20]);
        JobLead::create(['title' => 'Skipped', 'score' => 95, 'status' => LeadStatus::Skipped]);

        $this->artisan('leads:digest')->assertSuccessful();
        $this->artisan('leads:digest')->assertSuccessful();

        $messages = implode("\n", $this->telegramMessages());
        $this->assertStringContainsString('Great', $messages);
        $this->assertStringNotContainsString('Weak', $messages);
        $this->assertStringNotContainsString('Skipped', $messages);
        $this->assertSame(1, substr_count($messages, 'Great'));
    }
}
