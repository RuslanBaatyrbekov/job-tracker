<?php

namespace App\Jobs;

use App\Enums\NoteType;
use App\Models\Email;
use App\Models\JobApplication;
use App\Services\Ai\CareerAssistant;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Status moved to "interview" -> build a preparation pack and send it to Telegram.
 */
class GenerateInterviewPrepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [120, 600];

    public function __construct(public int $applicationId, public ?int $emailId = null) {}

    public function handle(CareerAssistant $assistant, TelegramService $telegram): void
    {
        $application = JobApplication::with('jobCategory')->find($this->applicationId);
        if (! $application) {
            return;
        }

        $alreadyPrepared = $application->notes()->where('type', NoteType::InterviewPrep)->exists();
        if ($alreadyPrepared) {
            return;
        }

        $invite = $this->emailId ? Email::find($this->emailId)?->body_text : null;
        $prep = $assistant->interviewPrep($application, $invite);

        $application->notes()->create([
            'type' => NoteType::InterviewPrep,
            'content' => $prep,
        ]);

        $telegram->send('🧠 <b>Подготовка к интервью: '.e($application->company_name)."</b>\n\n".TelegramService::markdownToHtml($prep));
    }
}
