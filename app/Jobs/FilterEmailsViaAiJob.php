<?php

namespace App\Jobs;

use App\Models\Email;
use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FilterEmailsViaAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(GeminiService $geminiService): void
    {
        $pendingEmails = Email::where('status', 'pending_subject_scan')
            ->limit(50)
            ->get();

        if ($pendingEmails->isEmpty()) {
            return;
        }

        $emailsForAi = $pendingEmails->map(function ($email) {
            return [
                'id' => $email->id,
                'subject' => $email->subject,
                'from_name' => $email->from_name,
            ];
        })->toArray();

        $relevantIds = $geminiService->filterJobRelatedEmails($emailsForAi);

        foreach ($pendingEmails as $email) {
            if (in_array($email->id, $relevantIds)) {
                $email->status = 'pending_body_analysis';
                $email->save();
                AnalyzeEmailBodyJob::dispatch($email->id);
            } else {
                $email->status = 'irrelevant';
                $email->save();
            }
        }
    }
}
