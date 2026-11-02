<?php

namespace App\Services;

use App\Models\JobApplication;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public function sendApplicationAlert(JobApplication $application): void
    {
        $token = config('services.telegram.token');
        $chatId = config('services.telegram.chat_id');

        if (!$token || !$chatId) {
            return;
        }

        $message = $this->buildMessage($application);

        $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id'    => $chatId,
            'text'       => $message,
            'parse_mode' => 'HTML',
        ]);

        if ($response->failed()) {
            Log::error('Telegram API Error: ' . $response->body());
        }
    }

    private function buildMessage(JobApplication $application): string
    {
        $category = $application->jobCategory ? $application->jobCategory->name : 'Без категории';
        $company = htmlspecialchars($application->company_name);
        $position = htmlspecialchars($application->position);

        $aiAdvice = $application->notes()->where('type', 'ai_advice')->first()?->content;
        $adviceText = $aiAdvice ? "\n\n💡 <b>ИИ:</b> <i>" . htmlspecialchars($aiAdvice) . "</i>" : '';

        return match ($application->status) {
            'interview' => "🎯 <b>НОВОЕ ИНТЕРВЬЮ!</b>\n🏢 {$company}\n💼 {$position} ({$category}){$adviceText}",
            'offer'     => "🏆 <b>ОФФЕР!</b>\n🏢 {$company}\n💼 {$position} ({$category}){$adviceText}",
            'rejected'  => "❌ <b>Отказ</b>\n🏢 {$company}\n💼 {$position} ({$category}){$adviceText}",
            default     => "📝 <b>Обновление статуса: {$application->status}</b>\n🏢 {$company}\n💼 {$position} ({$category}){$adviceText}",
        };
    }
}
