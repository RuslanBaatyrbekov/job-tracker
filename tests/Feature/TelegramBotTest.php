<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\LeadStatus;
use App\Models\JobApplication;
use App\Models\JobLead;
use App\Services\TelegramBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramBotTest extends TestCase
{
    use RefreshDatabase;

    private function message(string $text, string $chatId = '42'): array
    {
        return ['update_id' => 1, 'message' => ['chat' => ['id' => $chatId], 'text' => $text]];
    }

    public function test_foreign_chats_are_ignored(): void
    {
        $this->fakeTelegramOnly();

        app(TelegramBot::class)->handle($this->message('/stats', '999'));

        $this->assertSame([], $this->telegramMessages());
    }

    public function test_stats_command(): void
    {
        $this->fakeTelegramOnly();
        JobApplication::create(['company_name' => 'A', 'position' => 'Dev', 'status' => ApplicationStatus::Interview, 'applied_at' => now()]);
        JobApplication::create(['company_name' => 'B', 'position' => 'Dev', 'status' => ApplicationStatus::Applied, 'applied_at' => now()]);

        app(TelegramBot::class)->handle($this->message('/stats'));

        $this->assertStringContainsString('Интервью: 1 (50%)', $this->telegramMessages()[0]);
    }

    public function test_applied_button_converts_lead(): void
    {
        $this->fakeTelegramOnly();
        $lead = JobLead::create(['title' => 'Laravel Dev', 'company_name' => 'Acme']);

        app(TelegramBot::class)->handle([
            'update_id' => 2,
            'callback_query' => ['id' => 'cb1', 'data' => "applied:{$lead->id}", 'message' => ['chat' => ['id' => '42']]],
        ]);

        $this->assertSame(LeadStatus::Applied, $lead->fresh()->status);
        $this->assertSame(1, JobApplication::count());
    }

    public function test_pasted_vacancy_text_becomes_scored_lead(): void
    {
        $this->fakeGemini(
            ['title' => 'Senior Laravel Developer', 'company' => 'Acme', 'location' => 'Frankfurt', 'url' => null, 'description' => 'Laravel, Vue, Docker'],
            ['score' => 80, 'reason' => 'ok', 'red_flags' => [], 'category_name' => null, 'work_mode' => 'hybrid', 'german_level' => 'B1', 'language' => 'de'],
        );

        app(TelegramBot::class)->handle($this->message(str_repeat('Wir suchen einen Laravel Entwickler. ', 10)));

        $lead = JobLead::sole();
        $this->assertSame('Senior Laravel Developer', $lead->title);
        $this->assertSame(80, $lead->score);
    }
}
