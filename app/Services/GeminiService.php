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
        // Выгружаем всю базу знаний: категории, резюме и глобальные стратегии
        $categoriesInfo = \App\Models\JobCategory::all()->map(function ($cat) {
            return [
                'name' => $cat->name,
                'resume' => $cat->resume_text ?? 'Нет резюме',
                'global_strategy' => $cat->ai_strategy_report ?? 'Стратегия еще не сформирована'
            ];
        });

        $categoriesJson = json_encode($categoriesInfo, JSON_UNESCAPED_UNICODE);
        $categoryNames = $categoriesInfo->pluck('name')->implode(', ');

        return <<<PROMPT
Ты — карьерный консультант. Проанализируй ответ от работодателя.

ТЕМА ПИСЬМА: "{$subject}"
ТЕКСТ ПИСЬМА:
{$bodyText}

ОБЩАЯ БАЗА ЗНАНИЙ (Доступные категории, наши резюме и текущие стратегии):
{$categoriesJson}

ЗАДАЧА:
1. Определи, к какой категории из списка [{$categoryNames}] относится вакансия.
2. Определи статус (applied, rejected, interview, offer).
3. Если это отказ, вытащи причину.
4. Сформируй совет (ai_advice) ИМЕННО ПО ЭТОМУ ПИСЬМУ, опираясь на Базу Знаний выбранной категории.
   - Если это стандартная отписка, сверься со стратегией и коротко подтверди, что делать дальше.
   - Если в письме есть уникальный фидбек, сравни его с нашим резюме и дай четкий шаг.
   - Не придумывай советы из воздуха, опирайся на контекст стратегии.

Верни СТРОГО JSON:
{
  "company_name": "Название компании (иначе null)",
  "position": "Название должности (иначе null)",
  "status": "applied | rejected | interview | offer",
  "rejection_reason": "Причина отказа (иначе null)",
  "category_name": "Точное название категории (иначе null)",
  "ai_advice": "Умный совет с учетом глобальной стратегии (иначе null)"
}
PROMPT;
    }
}
