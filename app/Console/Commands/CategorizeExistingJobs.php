<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\JobApplication;
use App\Models\JobCategory;
use Illuminate\Support\Facades\Http;

class CategorizeExistingJobs extends Command
{
    protected $signature = 'jobs:categorize-existing';
    protected $description = 'Автоматически распределяет старые отклики по категориям с помощью Gemini';

    public function handle()
    {
        $categories = JobCategory::all();

        if ($categories->isEmpty()) {
            $this->error('Нет категорий.');
            return;
        }

        $applications = JobApplication::whereNull('job_category_id')->get();
        if ($applications->isEmpty()) {
            $this->info('Все отклики уже имеют категории!');
            return;
        }

        // Очищаем пробелы по краям для промпта
        $categoryNames = $categories->map(fn($c) => trim($c->name))->implode(', ');
        $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key=' . config('services.gemini.key');

        $this->info("Найдено {$applications->count()} откликов. Категории: [{$categoryNames}]");

        foreach ($applications as $app) {
            $this->info("Анализируем: {$app->position} (Компания: {$app->company_name})...");

            $prompt = <<<PROMPT
Ты — эксперт по рекрутингу. Вакансия: "{$app->position}" в "{$app->company_name}".
Выбери НАИБОЛЕЕ подходящую категорию из списка: [{$categoryNames}].
Даже если нет 100% совпадения (например, системный администратор), выбери ту, что ближе всего по смыслу (например, инженерная). Не возвращай null.
Верни СТРОГО JSON:
{"category_name": "Точное название категории"}
PROMPT;

            // Повторяем запрос до 3 раз с паузой 5 секунд при ошибке (защита от rate limit)
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->retry(3, 5000)
                ->post($apiUrl, [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['response_mime_type' => 'application/json']
                ]);

            if ($response->successful()) {
                $result = json_decode($response->json('candidates.0.content.parts.0.text'), true);
                $aiCategory = trim($result['category_name'] ?? '');

                if (!empty($aiCategory)) {
                    // Ищем категорию без учета регистра и лишних пробелов
                    $category = $categories->first(fn($c) => strtolower(trim($c->name)) === strtolower($aiCategory));

                    if ($category) {
                        $app->update(['job_category_id' => $category->id]);
                        $this->info(" [+] Назначена: {$category->name}");
                    } else {
                        $this->warn(" [-] ИИ вернул несуществующую: {$aiCategory}");
                    }
                } else {
                    $this->warn(" [-] ИИ не смог подобрать.");
                }
            } else {
                $this->error("Ошибка API: " . $response->status() . " " . $response->body());
            }

            // Базовая пауза между запросами 4 секунды (хватит для 15 RPM)
            sleep(4);
        }

        $this->info('Синхронизация завершена!');
    }
}
