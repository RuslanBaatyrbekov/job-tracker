<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent';

    /**
     * Пакетный анализ тем писем.
     * Ожидает на вход массив писем и возвращает массив ID релевантных.
     */
    public function filterJobRelatedEmails(array $emails): array
    {
        $prompt = $this->buildPromptForFiltering($emails);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($this->apiUrl . '?key=' . config('services.gemini.key'), [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            // Заставляем Gemini вернуть строго JSON
            'generationConfig' => [
                'response_mime_type' => 'application/json',
            ]
        ]);

        if ($response->failed()) {
            Log::error('Gemini API Error (Filter): ' . $response->body());
            return [];
        }

        $result = $response->json('candidates.0.content.parts.0.text');

        // Декодируем строку JSON от Gemini в PHP массив
        $parsed = json_decode($result, true);

        return $parsed['relevant_ids'] ?? [];
    }

    /**
     * Формируем промпт для пачки писем
     */
    private function buildPromptForFiltering(array $emails): string
    {
        $jsonEmails = json_encode($emails, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Ты — помощник разработчика, который ищет работу.
Твоя задача — отфильтровать список входящих писем, опираясь ТОЛЬКО на их темы (subject) и отправителя (from_name).
Тебе нужно найти письма, которые касаются:
- Откликов на вакансии (application, applied).
- Приглашений на интервью (interview, call, screening).
- Отказов (rejected, unfortunately, update on your application).
- Тестовых заданий.
- Офферов.

Игнорируй: рекламные рассылки, спам, системные уведомления GitHub/GitLab, личную переписку.

Вот JSON массив писем:
{$jsonEmails}

Верни ответ СТРОГО в формате JSON:
{
  "relevant_ids": [1, 5, 12]
}
PROMPT;
    }

    /**
     * Глубокий анализ тела письма для извлечения данных об отклике.
     */
    public function analyzeEmailBody(string $subject, string $bodyText): ?array
    {
        $prompt = $this->buildPromptForBodyAnalysis($subject, $bodyText);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($this->apiUrl . '?key=' . config('services.gemini.key'), [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
            ]
        ]);

        if ($response->failed()) {
            Log::error('Gemini API Error (Body Analysis): ' . $response->body());
            return null;
        }

        $result = $response->json('candidates.0.content.parts.0.text');

        return json_decode($result, true);
    }

    private function buildPromptForBodyAnalysis(string $subject, string $bodyText): string
    {
        return <<<PROMPT
Ты — карьерный консультант и парсер данных.
Проанализируй текст письма от работодателя (или автоматической системы найма).

Тема письма: "{$subject}"
Текст письма:
{$bodyText}

Твоя задача — извлечь данные и вернуть их СТРОГО в формате JSON.
Используй следующую структуру:
{
  "company_name": "Название компании (если не найдено, верни null)",
  "position": "Название должности (если не найдено, верни null)",
  "status": "Один из вариантов: 'applied' (просто подтверждение отклика), 'rejected' (отказ), 'interview' (приглашение на собеседование/тестовое), 'offer' (предложение работы)",
  "rejection_reason": "Если статус 'rejected', коротко опиши причину отказа исходя из текста (если указана). Иначе null.",
  "ai_advice": "Твой совет соискателю. Например, 'Подтянуть знание Docker' или 'Написать фоллоу-ап письмо'. Иначе null."
}
PROMPT;
    }
}
