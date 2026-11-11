<?php

namespace App\Console\Commands;

use App\Enums\ApplicationStatus;
use App\Enums\NoteType;
use App\Exceptions\AiRequestException;
use App\Models\JobApplication;
use App\Services\Ai\CareerAssistant;
use App\Services\TelegramService;
use Illuminate\Console\Command;

/**
 * Applications without any answer:
 *  - after N days: remind to send a follow-up (with an AI draft),
 *  - after M days: mark as "no response" (ghosted) so the funnel is honest.
 */
class CheckFollowUps extends Command
{
    protected $signature = 'applications:follow-ups';

    protected $description = 'Remind about follow-ups and mark ghosted applications';

    public function handle(CareerAssistant $assistant, TelegramService $telegram): int
    {
        $remindAfter = config('job-search.follow_up.remind_after_days');
        $ghostedAfter = config('job-search.follow_up.ghosted_after_days');

        $ghosted = JobApplication::awaitingReply()
            ->where('last_activity_at', '<', now()->subDays($ghostedAfter))
            ->get();

        foreach ($ghosted as $application) {
            $application->update(['status' => ApplicationStatus::NoResponse]);
        }

        if ($ghosted->isNotEmpty()) {
            $telegram->send("👻 <b>Без ответа {$ghostedAfter}+ дней:</b> ".$ghosted->count().' откликов помечены как «Нет ответа».');
        }

        $toRemind = JobApplication::awaitingReply()
            ->whereNull('follow_up_reminded_at')
            ->whereNull('follow_up_sent_at')
            ->where('last_activity_at', '<', now()->subDays($remindAfter))
            ->orderBy('applied_at')
            ->limit(5) // a few per day is enough, more is spam
            ->get();

        foreach ($toRemind as $application) {
            try {
                $draft = $assistant->followUpDraft($application);
            } catch (AiRequestException $e) {
                $this->error("AI error for #{$application->id}: {$e->getMessage()}");

                continue;
            }

            $application->notes()->create(['type' => NoteType::FollowUpDraft, 'content' => $draft]);
            $application->update(['follow_up_reminded_at' => now()]);

            $contact = $application->sourceEmail?->from_address;
            $telegram->send(
                "⏰ <b>Пора напомнить о себе</b>\n🏢 ".e($application->company_name).' — '.e($application->position)
                ."\nОтклик ".$application->applied_at?->diffForHumans().($contact ? "\n✉️ ".e($contact) : '')
                ."\n\n<pre>".e($draft).'</pre>',
                [[['text' => '📤 Отправил follow-up', 'callback_data' => "followed:{$application->id}"]]],
            );
        }

        $this->info("Ghosted: {$ghosted->count()}, reminders: {$toRemind->count()}");

        return self::SUCCESS;
    }
}
