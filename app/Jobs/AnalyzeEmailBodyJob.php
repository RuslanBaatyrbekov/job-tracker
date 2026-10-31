<?php

namespace App\Jobs;

use App\Models\Email;
use App\Models\JobApplication;
use App\Models\ApplicationNote;
use App\Services\GeminiService;
use App\Services\ImapService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeEmailBodyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $emailId;

    public function __construct(int $emailId)
    {
        $this->emailId = $emailId;
    }

    public function handle(ImapService $imapService, GeminiService $geminiService): void
    {
        $email = Email::find($this->emailId);

        if (!$email || $email->status !== 'pending_body_analysis') {
            return;
        }

        // 1. Докачиваем тело письма, если его еще нет
        if (empty($email->body_text)) {
            $downloaded = $imapService->fetchBodyForEmail($email);
            if (!$downloaded) {
                Log::warning("Не удалось скачать тело письма ID: {$email->id}");
                return;
            }
        }

        // 2. Отправляем текст в ИИ
        $aiResult = $geminiService->analyzeEmailBody($email->subject, $email->body_text);

        if (!$aiResult) {
            return;
        }

        $categoryId = null;
        if (!empty($aiResult['category_name'])) {
            $category = \App\Models\JobCategory::where('name', $aiResult['category_name'])->first();
            if ($category) {
                $categoryId = $category->id;
            }
        }

        $application = JobApplication::create([
            'source_email_id' => $email->id,
            'job_category_id' => $categoryId,
            'company_name'    => $aiResult['company_name'] ?? 'Неизвестная компания',
            'position'        => $aiResult['position'] ?? 'Разработчик',
            'status'          => $aiResult['status'] ?? 'applied',
            'applied_at'      => $email->date,
            'ai_metadata'     => $aiResult,
        ]);

        // 4. Сохраняем причину отказа, если есть
        if (!empty($aiResult['rejection_reason'])) {
            ApplicationNote::create([
                'job_application_id' => $application->id,
                'type'               => 'rejection_reason',
                'content'            => $aiResult['rejection_reason'],
            ]);
        }

        // 5. Сохраняем советы ИИ
        if (!empty($aiResult['ai_advice'])) {
            ApplicationNote::create([
                'job_application_id' => $application->id,
                'type'               => 'ai_advice',
                'content'            => $aiResult['ai_advice'],
            ]);
        }

        // 6. Помечаем письмо как обработанное
        $email->update(['status' => 'processed']);
    }
}
