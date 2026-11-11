<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\EmailStatus;
use App\Exceptions\AiRequestException;
use App\Jobs\AnalyzeEmailBodyJob;
use App\Jobs\FilterEmailsViaAiJob;
use App\Jobs\GenerateInterviewPrepJob;
use App\Jobs\ParseJobAlertJob;
use App\Models\Email;
use App\Models\JobApplication;
use App\Models\Mailbox;
use App\Services\Ai\CareerAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class EmailPipelineTest extends TestCase
{
    use RefreshDatabase;

    private Mailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailbox = Mailbox::create([
            'email' => 'me@gmail.com',
            'host' => 'imap.gmail.com',
            'username' => 'me@gmail.com',
            'password' => 'secret',
        ]);
    }

    private function email(array $attributes = []): Email
    {
        static $uid = 1;

        return Email::create(array_merge([
            'mailbox_id' => $this->mailbox->id,
            'imap_uid' => (string) $uid++,
            'subject' => 'Your application',
            'from_address' => 'jobs@acme.de',
            'from_name' => 'Acme HR',
            'date' => now(),
            'status' => EmailStatus::PendingBodyAnalysis,
            'body_text' => 'Thank you for your application.',
        ], $attributes));
    }

    private function analysis(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Acme GmbH',
            'position' => 'Backend Developer (PHP/Laravel)',
            'status' => 'applied',
            'rejection_reason' => null,
            'category_name' => null,
            'ai_advice' => 'Ждём ответа.',
        ], $overrides);
    }

    public function test_filter_failure_keeps_emails_pending(): void
    {
        Sleep::fake();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('error', 500)]);
        $email = $this->email(['status' => EmailStatus::PendingSubjectScan]);

        try {
            (new FilterEmailsViaAiJob)->handle(app(CareerAssistant::class));
            $this->fail('Exception expected so the queue retries the job');
        } catch (AiRequestException) {
        }

        // Before the fix all emails became "irrelevant" here and were lost.
        $this->assertSame(EmailStatus::PendingSubjectScan, $email->fresh()->status);
    }

    public function test_filter_routes_replies_alerts_and_noise(): void
    {
        Queue::fake([AnalyzeEmailBodyJob::class, ParseJobAlertJob::class]);
        $reply = $this->email(['status' => EmailStatus::PendingSubjectScan]);
        $alert = $this->email(['status' => EmailStatus::PendingSubjectScan, 'subject' => '25 new jobs for PHP Developer']);
        $spam = $this->email(['status' => EmailStatus::PendingSubjectScan, 'subject' => 'Black Friday']);

        $this->fakeGemini(['relevant_ids' => [$reply->id], 'job_alert_ids' => [$alert->id]]);

        (new FilterEmailsViaAiJob)->handle(app(CareerAssistant::class));

        $this->assertSame(EmailStatus::PendingBodyAnalysis, $reply->fresh()->status);
        $this->assertSame(EmailStatus::PendingAlertParse, $alert->fresh()->status);
        $this->assertSame(EmailStatus::Irrelevant, $spam->fresh()->status);
        Queue::assertPushed(AnalyzeEmailBodyJob::class, fn ($job) => $job->emailId === $reply->id);
        Queue::assertPushed(ParseJobAlertJob::class, fn ($job) => $job->emailId === $alert->id);
    }

    public function test_rejection_updates_existing_application_instead_of_creating_duplicate(): void
    {
        $this->fakeGemini(
            $this->analysis(),
            $this->analysis(['company_name' => 'ACME', 'status' => 'rejected', 'rejection_reason' => 'Need more German']),
        );

        AnalyzeEmailBodyJob::dispatchSync($this->email()->id);
        AnalyzeEmailBodyJob::dispatchSync($this->email(['subject' => 'Update on your application'])->id);

        $this->assertSame(1, JobApplication::count());
        $application = JobApplication::first();
        $this->assertSame(ApplicationStatus::Rejected, $application->status);
        $this->assertSame(['applied', 'rejected'], $application->statusChanges->pluck('to_status')->all());
        $this->assertSame(1, $application->notes()->where('type', 'rejection_reason')->count());
        $this->assertCount(2, $this->telegramMessages());
    }

    public function test_late_confirmation_does_not_downgrade_interview_and_is_not_notified(): void
    {
        Queue::fake([GenerateInterviewPrepJob::class]);
        $this->fakeGemini(
            $this->analysis(['status' => 'interview']),
            $this->analysis(['status' => 'applied']),
        );

        AnalyzeEmailBodyJob::dispatchSync($this->email()->id);
        AnalyzeEmailBodyJob::dispatchSync($this->email()->id);

        $this->assertSame(ApplicationStatus::Interview, JobApplication::sole()->status);
        $this->assertCount(1, $this->telegramMessages());
        Queue::assertPushed(GenerateInterviewPrepJob::class, 1);
    }

    public function test_matching_by_company_domain_when_name_differs(): void
    {
        $this->fakeGemini(
            $this->analysis(['company_name' => 'Acme Digital Solutions']),
            $this->analysis(['company_name' => 'Acme', 'status' => 'rejected']),
        );

        AnalyzeEmailBodyJob::dispatchSync($this->email()->id);
        AnalyzeEmailBodyJob::dispatchSync($this->email()->id);

        $this->assertSame(1, JobApplication::count());
    }

    public function test_different_role_at_same_company_is_a_new_application(): void
    {
        $this->fakeGemini(
            $this->analysis(),
            $this->analysis(['position' => 'Head of Marketing']),
        );

        AnalyzeEmailBodyJob::dispatchSync($this->email()->id);
        AnalyzeEmailBodyJob::dispatchSync($this->email()->id);

        $this->assertSame(2, JobApplication::count());
    }

    public function test_failed_analysis_marks_email_as_failed(): void
    {
        $email = $this->email();
        (new AnalyzeEmailBodyJob($email->id))->failed(new \RuntimeException('boom'));

        $this->assertSame(EmailStatus::Failed, $email->fresh()->status);
    }
}
