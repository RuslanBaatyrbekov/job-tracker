<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\JobCategory;
use App\Models\ApplicationNote;
use Illuminate\Support\Facades\Http;

class GenerateAiStrategy extends Command
{
    protected $signature = 'ai:strategy';
    protected $description = 'Непрерывный смарт-анализ резюме и новых отказов с учетом памяти ИИ';

    public function handle()
    {
        $categories = JobCategory::whereNotNull('resume_text')->get();

        if ($categories->isEmpty()) {
            $this->warn('Нет категорий с заполненным резюме.');
            return;
        }

        foreach ($categories as $category) {
            $this->info("Анализ категории: {$category->name}...");

            // 1. Ищем только НОВЫЕ отказы с момента последнего анализа
            $query = ApplicationNote::where('type', 'rejection_reason')
                ->whereHas('jobApplication', fn($q) => $q->where('job_category_id', $category->id));

            if ($category->last_analyzed_at) {
                $query->where('created_at', '>', $category->last_analyzed_at);
            }

            $newRejections = $query->latest()->take(20)->get();
            $hasNewRejections = $newRejections->isNotEmpty();

            // 2. Проверяем, менял ли пользователь резюме после последнего анализа
            $resumeUpdated = $category->last_analyzed_at && ($category->updated_at > $category->last_analyzed_at);

            // 3. Защита от лишних запросов: если ничего не произошло, пропускаем
            if ($category->last_analyzed_at && !$hasNewRejections && !$resumeUpdated) {
                $this->info(" [-] Нет новых отказов и резюме не менялось. Скипаем (экономим API).");
                continue;
            }

            $rejectionsText = $hasNewRejections
                ? $newRejections->map(fn($r) => "- Должность: {$r->jobApplication->position}. Отказ: {$r->content}")->implode("\n")
                : "Новых отказов с момента прошлого ревью не поступало.";

            // 4. Формируем блок "Памяти ИИ"
            $memoryBlock = "";
            if ($category->ai_strategy_report) {
                $memoryBlock = <<<TEXT
ТВОЙ ПРОШЛЫЙ ОТЧЕТ И СОВЕТЫ:
---
{$category->ai_strategy_report}
---
ОБЯЗАТЕЛЬНО учти его. Проверь, исправил ли кандидат текущее резюме согласно твоим прошлым советам. Если советы внедрены, переходи к следующему этапу. Если нет — жестко укажи на это.
TEXT;
            }

            $prompt = <<<PROMPT
Ты — строгий и системный ИИ-ментор по карьере. Это наш непрерывный процесс трудоустройства.
Категория вакансий: {$category->name}.

{$memoryBlock}

ТЕКУЩАЯ ВЕРСИЯ РЕЗЮМЕ КАНДИДАТА:
---
{$category->resume_text}
---

НОВЫЕ ПРИЧИНЫ ОТКАЗОВ (события с прошлого сеанса):
---
{$rejectionsText}
---

Твоя задача на этот сеанс:
1. Оценить прогресс. ВНИМАНИЕ: Если изменения в резюме минимальны (опечатки, пара символов), просто укажи на это в ОДНОМ предложении и не читай нотации.
2. Обозначить текущую главную проблему (1-2 предложения).
3. Дать максимум 3 коротких, рубленых шага к действию (bullet points).
Отвечай МАКСИМАЛЬНО КРАТКО (строго до 150 слов) и сухо. Формат Markdown. Никаких длинных вступлений, философских рассуждений, нравоучений и воды.
PROMPT;

            $response = Http::post('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key=' . config('services.gemini.key'), [
                'contents' => [['parts' => [['text' => $prompt]]]]
            ]);

            $advice = $response->json('candidates.0.content.parts.0.text');

            if ($advice) {
                // Обновляем отчет и ставим свежую метку времени (без изменения updated_at самой модели)
                $category->timestamps = false;
                $category->update([
                    'ai_strategy_report' => $advice,
                    'last_analyzed_at' => now(),
                ]);
                $category->timestamps = true;

                $this->info(" [+] Смарт-отчет обновлен.");
            } else {
                $this->error(" [-] Ошибка API Gemini.");
            }

            sleep(2);
        }

        $this->info('Смарт-анализ завершен!');
    }
}
