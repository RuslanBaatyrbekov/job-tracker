<?php

namespace App\Services;

use App\Enums\NoteType;
use App\Models\JobApplication;
use App\Models\JobLead;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private const MAX_LENGTH = 4000; // Telegram limit is 4096, keep a margin for tags

    public function isConfigured(): bool
    {
        return (bool) (config('services.telegram.token') && config('services.telegram.chat_id'));
    }

    /**
     * Send an HTML message. Long texts are split into several messages.
     * Never throws: a Telegram outage must not break the email pipeline.
     */
    public function send(string $html, ?array $inlineKeyboard = null, ?string $chatId = null): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $chunks = $this->split($html);
        $last = array_key_last($chunks);

        foreach ($chunks as $i => $chunk) {
            $payload = [
                'chat_id' => $chatId ?? config('services.telegram.chat_id'),
                'text' => $chunk,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ];

            if ($inlineKeyboard && $i === $last) {
                $payload['reply_markup'] = ['inline_keyboard' => $inlineKeyboard];
            }

            $this->call('sendMessage', $payload);
        }
    }

    public function sendApplicationAlert(JobApplication $application, bool $isUpdate = false): void
    {
        $category = $application->jobCategory?->name ?? 'Без категории';
        $company = e($application->company_name);
        $position = e($application->position);

        $advice = $application->notes()->where('type', NoteType::AiAdvice)->latest('id')->value('content');
        $adviceText = $advice ? "\n\n💡 <b>ИИ:</b> <i>".e($advice).'</i>' : '';

        $header = match ($application->status->value) {
            'interview' => '🎯 <b>НОВОЕ ИНТЕРВЬЮ!</b>',
            'offer' => '🏆 <b>ОФФЕР!</b>',
            'rejected' => '❌ <b>Отказ</b>',
            'applied' => $isUpdate ? '📝 <b>Обновление</b>' : '📨 <b>Отклик зарегистрирован</b>',
            default => '📝 <b>Статус: '.e($application->status->getLabel()).'</b>',
        };

        $this->send("{$header}\n🏢 {$company}\n💼 {$position} ({$category}){$adviceText}\n<code>#app{$application->id}</code>");
    }

    public function sendLeadCard(JobLead $lead): void
    {
        $this->send($this->leadCard($lead), $this->leadKeyboard($lead));
    }

    public function leadCard(JobLead $lead): string
    {
        $flags = $lead->red_flags ? "\n⚠️ ".e(implode('; ', $lead->red_flags)) : '';
        $meta = array_filter([
            $lead->location,
            $lead->work_mode,
            $lead->german_level ? 'DE '.$lead->german_level : null,
            $lead->source?->getLabel(),
        ]);

        $title = $lead->url
            ? '<a href="'.e($lead->url).'">'.e($lead->title).'</a>'
            : e($lead->title);

        return "<b>{$lead->score}</b>/100 · {$title}\n🏢 ".e($lead->company_name ?? '—')
            .($meta ? ' · '.e(implode(' · ', $meta)) : '')
            .($lead->score_reason ? "\n<i>".e($lead->score_reason).'</i>' : '')
            .$flags;
    }

    public function leadKeyboard(JobLead $lead): array
    {
        return [[
            ['text' => '✍️ Письмо', 'callback_data' => "cover:{$lead->id}"],
            ['text' => '✅ Откликнулся', 'callback_data' => "applied:{$lead->id}"],
            ['text' => '🙈 Пропустить', 'callback_data' => "skip:{$lead->id}"],
        ]];
    }

    public function answerCallback(string $callbackId, string $text = ''): void
    {
        $this->call('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $text]);
    }

    /**
     * Long polling for the bot. Returns raw updates.
     */
    public function getUpdates(int $offset, int $timeout = 25): array
    {
        $token = config('services.telegram.token');
        if (! $token) {
            return [];
        }

        try {
            $response = Http::timeout($timeout + 10)
                ->get("https://api.telegram.org/bot{$token}/getUpdates", [
                    'offset' => $offset,
                    'timeout' => $timeout,
                    'allowed_updates' => json_encode(['message', 'callback_query']),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Telegram getUpdates failed: '.$e->getMessage());

            return [];
        }

        return $response->json('result') ?? [];
    }

    /**
     * Very small Markdown -> Telegram HTML converter for AI answers.
     */
    public static function markdownToHtml(string $markdown): string
    {
        $html = e($markdown);
        $html = preg_replace('/^#{1,6}\s*(.+)$/m', '<b>$1</b>', $html);
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $html);
        $html = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/', '<i>$1</i>', $html);
        $html = preg_replace('/`([^`\n]+)`/', '<code>$1</code>', $html);
        $html = preg_replace('/^\s*[-*]\s+/m', '• ', $html);

        return $html;
    }

    private function call(string $method, array $payload): void
    {
        $token = config('services.telegram.token');
        if (! $token) {
            return;
        }

        try {
            // Retry network hiccups / 5xx / 429, but don't throw: the result is checked below.
            $response = Http::timeout(15)
                ->retry(3, 1000, fn ($e) => ! ($e instanceof RequestException)
                    || $e->response->serverError() || $e->response->status() === 429, throw: false)
                ->post("https://api.telegram.org/bot{$token}/{$method}", $payload);

            if ($response->failed()) {
                Log::error("Telegram API error ({$method}): ".$response->body());
            }
        } catch (\Throwable $e) {
            Log::error("Telegram API unreachable ({$method}): ".$e->getMessage());
        }
    }

    /** @return string[] */
    private function split(string $html): array
    {
        if (mb_strlen($html) <= self::MAX_LENGTH) {
            return [$html];
        }

        $chunks = [];
        $current = '';

        foreach (preg_split('/(\n\n)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE) as $part) {
            if (mb_strlen($current.$part) > self::MAX_LENGTH && $current !== '') {
                $chunks[] = trim($current);
                $current = '';
            }
            // A single huge paragraph: hard cut.
            while (mb_strlen($part) > self::MAX_LENGTH) {
                $chunks[] = mb_substr($part, 0, self::MAX_LENGTH);
                $part = mb_substr($part, self::MAX_LENGTH);
            }
            $current .= $part;
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }
}
