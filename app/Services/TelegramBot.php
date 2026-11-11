<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\LeadStatus;
use App\Exceptions\AiRequestException;
use App\Models\JobApplication;
use App\Models\JobLead;
use Illuminate\Support\Facades\Log;

/**
 * Handles incoming Telegram updates. Only the owner's chat (TELEGRAM_CHAT_ID) is served.
 */
class TelegramBot
{
    public function __construct(
        private readonly TelegramService $telegram,
        private readonly LeadActions $leads,
        private readonly FunnelStats $stats,
    ) {}

    public function handle(array $update): void
    {
        $chatId = (string) ($update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? '');

        if ($chatId === '' || $chatId !== (string) config('services.telegram.chat_id')) {
            Log::warning('Telegram update from unknown chat ignored: '.$chatId);

            return;
        }

        try {
            if (isset($update['callback_query'])) {
                $this->handleCallback($update['callback_query']);
            } elseif (isset($update['message']['text'])) {
                $this->handleMessage(trim($update['message']['text']));
            }
        } catch (AiRequestException $e) {
            $this->telegram->send('🤖 ИИ сейчас недоступен, попробуй позже.');
            Log::warning('Telegram bot AI error: '.$e->getMessage());
        }
    }

    private function handleMessage(string $text): void
    {
        [$command, $args] = array_pad(preg_split('/\s+/', $text, 2), 2, '');
        $command = strtolower(explode('@', $command)[0]);

        match ($command) {
            '/start', '/help' => $this->help(),
            '/stats' => $this->sendStats(),
            '/leads' => $this->sendLeads(),
            '/add' => $this->addLead($args),
            '/cover' => $this->cover((int) $args),
            '/applied' => $this->applied((int) $args),
            '/skip' => $this->skip((int) $args),
            '/followed' => $this->followed((int) $args),
            // Anything long without a command = a pasted vacancy.
            default => mb_strlen($text) > 200 || preg_match('~^https?://~', $text)
                ? $this->addLead($text)
                : $this->help(),
        };
    }

    private function handleCallback(array $callback): void
    {
        [$action, $id] = array_pad(explode(':', $callback['data'] ?? '', 2), 2, 0);
        $this->telegram->answerCallback($callback['id'], '⏳');

        match ($action) {
            'cover' => $this->cover((int) $id),
            'applied' => $this->applied((int) $id),
            'skip' => $this->skip((int) $id),
            'followed' => $this->followed((int) $id),
            default => null,
        };
    }

    private function help(): void
    {
        $this->telegram->send(implode("\n", [
            '<b>Job Tracker bot</b>',
            '/stats — воронка за неделю и за всё время',
            '/leads — лучшие новые вакансии',
            '/add &lt;текст или ссылка&gt; — добавить вакансию и оценить её',
            '/cover ID — сопроводительное + правки резюме под вакансию',
            '/applied ID — отметить, что откликнулся',
            '/skip ID — пропустить вакансию',
            '/followed ID — отметить, что отправил follow-up по отклику',
            '',
            'Можно просто прислать текст вакансии или ссылку.',
        ]));
    }

    private function sendStats(): void
    {
        $all = $this->stats->summary();
        $week = $this->stats->summary(since: now()->startOfWeek());
        $goal = config('job-search.weekly_goal');

        $bySource = collect($this->stats->bySource())
            ->map(fn ($s, $name) => "• {$name}: {$s['applications']} → ответ {$s['response_rate']}%, интервью {$s['interview_rate']}%")
            ->implode("\n");

        $this->telegram->send(
            "📊 <b>Эта неделя:</b> {$week['applications']}/{$goal} откликов, интервью: {$week['interviews']}\n\n"
            ."<b>Всего:</b> {$all['applications']} откликов\n"
            ."Ответили: {$all['responded']} ({$all['response_rate']}%)\n"
            ."Интервью: {$all['interviews']} ({$all['interview_rate']}%)\n"
            ."Офферы: {$all['offers']} · Ждут: {$all['waiting']} · Без ответа: {$all['no_response']}\n\n"
            ."<b>По источникам:</b>\n".e($bySource ?: '—')
        );
    }

    private function sendLeads(): void
    {
        $leads = JobLead::whereIn('status', [LeadStatus::New, LeadStatus::Shortlisted])
            ->whereNotNull('score')
            ->orderByDesc('score')
            ->limit(5)
            ->get();

        if ($leads->isEmpty()) {
            $this->telegram->send('Новых вакансий нет. Подпишись на job alerts LinkedIn / StepStone / Indeed на этот ящик.');

            return;
        }

        $leads->each(fn (JobLead $lead) => $this->telegram->sendLeadCard($lead));
    }

    private function addLead(string $input): void
    {
        if (trim($input) === '') {
            $this->telegram->send('Пришли текст вакансии или ссылку: <code>/add https://...</code>');

            return;
        }

        $lead = $this->leads->createFromText($input);
        $hint = $lead->description ? '' : "\n\nℹ️ Текст вакансии не удалось получить — оценка только по названию. Пришли текст целиком для точной оценки.";

        $this->telegram->send('➕ <b>Добавлено</b> #'.$lead->id."\n".$this->telegram->leadCard($lead).$hint, $this->telegram->leadKeyboard($lead));
    }

    private function cover(int $leadId): void
    {
        $lead = JobLead::find($leadId);
        if (! $lead) {
            $this->telegram->send("Вакансия #{$leadId} не найдена.");

            return;
        }

        $this->telegram->send('✍️ Готовлю письмо для «'.e($lead->title).'»…');
        $text = $this->leads->tailor($lead);

        $this->telegram->send(TelegramService::markdownToHtml($text), [[
            ['text' => '✅ Откликнулся', 'callback_data' => "applied:{$lead->id}"],
        ]]);
    }

    private function applied(int $leadId): void
    {
        $lead = JobLead::find($leadId);
        if (! $lead) {
            $this->telegram->send("Вакансия #{$leadId} не найдена.");

            return;
        }

        $application = $this->leads->markApplied($lead);
        $week = $this->stats->appliedThisWeek();

        $this->telegram->send('✅ Отклик #'.$application->id.' сохранён: '.e($application->company_name)
            ."\nНа этой неделе: {$week}/".config('job-search.weekly_goal'));
    }

    private function skip(int $leadId): void
    {
        $lead = JobLead::find($leadId);
        if ($lead) {
            $this->leads->skip($lead);
            $this->telegram->send('🙈 Пропущено: '.e($lead->title));
        }
    }

    private function followed(int $applicationId): void
    {
        $application = JobApplication::find($applicationId);
        if (! $application) {
            $this->telegram->send("Отклик #{$applicationId} не найден.");

            return;
        }

        $application->update(['follow_up_sent_at' => now(), 'last_activity_at' => now()]);
        if ($application->status === ApplicationStatus::NoResponse) {
            $application->update(['status' => ApplicationStatus::Applied]);
        }

        $this->telegram->send('📤 Отмечено: follow-up в '.e($application->company_name).'. Следующее напоминание не придёт.');
    }
}
