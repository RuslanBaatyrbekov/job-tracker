<?php

namespace App\Jobs;

use App\Enums\EmailStatus;
use App\Models\Email;
use App\Services\Ai\CareerAssistant;
use App\Services\ApplicationRecorder;
use App\Services\ImapService;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Stage 2: read the employer email, update / create the application, notify in Telegram.
 */
class AnalyzeEmailBodyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    /** @var int[] */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $emailId) {}

    public function handle(
        ImapService $imap,
        CareerAssistant $assistant,
        ApplicationRecorder $recorder,
        TelegramService $telegram,
    ): void {
        $email = Email::find($this->emailId);

        if (! $email || $email->status !== EmailStatus::PendingBodyAnalysis) {
            return;
        }

        if (empty($email->body_text) && ! $imap->fetchBodyForEmail($email)) {
            // Throw so the queue retries later (IMAP hiccup), instead of leaving the email stuck forever.
            throw new RuntimeException("Could not download body of email {$email->id}");
        }

        $analysis = $assistant->analyzeEmployerEmail(
            (string) $email->subject,
            trim($email->from_name.' <'.$email->from_address.'>'),
            (string) $email->body_text,
        );

        $result = $recorder->record($email, $analysis);
        $email->update(['status' => EmailStatus::Processed]);

        // Notify only about something new — not about the 3rd "we received your application".
        if ($result['created'] || $result['status_changed']) {
            $telegram->sendApplicationAlert($result['application']->fresh(), ! $result['created']);
        }
    }

    public function failed(Throwable $e): void
    {
        Email::whereKey($this->emailId)->update(['status' => EmailStatus::Failed]);
        Log::error("Email {$this->emailId} analysis failed: ".$e->getMessage());
    }
}
