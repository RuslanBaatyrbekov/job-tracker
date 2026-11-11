<?php

namespace Database\Seeders;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\LeadStatus;
use App\Enums\NoteType;
use App\Models\JobApplication;
use App\Models\JobCategory;
use App\Models\JobLead;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Fake but realistic data to try the dashboard without IMAP / Gemini:
 *   php artisan db:seed --class=DemoSeeder
 * Login: demo@example.com / password
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(['email' => 'demo@example.com'], ['name' => 'Demo', 'password' => bcrypt('password')]);

        $laravel = JobCategory::firstOrCreate(['name' => 'Laravel Backend'], [
            'color' => '#f59e0b',
            'resume_text' => "Backend Developer (PHP/Laravel), 3.5 years.\n- Built REST APIs for an enterprise CRM\n- High-load delivery platform: queues, WebSockets, Redis",
            'ai_strategy_report' => "**Прогресс:** резюме обновлено.\n\n**Узкое место:** отвечают 18%, но LinkedIn Easy Apply почти не даёт интервью.\n\n- Делай 70% откликов через сайты компаний и StepStone\n- Добавь метрики нагрузки в пункт про delivery platform\n- 2 follow-up в неделю по откликам старше 10 дней",
            'last_analyzed_at' => now(),
        ]);
        $iot = JobCategory::firstOrCreate(['name' => 'IoT / Connected Devices'], [
            'color' => '#10b981',
            'resume_text' => "Backend Developer (PHP/Laravel) — IoT & Connected Devices.\nESP32, BLE, GPS, PlatformIO.",
        ]);

        $companies = [
            ['Nordwerk Software GmbH', 'Backend Developer PHP/Laravel (m/w/d)', $laravel, ApplicationSource::StepStone, ApplicationStatus::Interview, 21],
            ['Rheinblick Digital', 'Laravel Developer', $laravel, ApplicationSource::LinkedIn, ApplicationStatus::Rejected, 18],
            ['Mainhafen Logistics', 'PHP Backend Engineer', $laravel, ApplicationSource::CompanySite, ApplicationStatus::Offer, 30],
            ['Taunus Cloud AG', 'Software Engineer Backend', $laravel, ApplicationSource::LinkedIn, ApplicationStatus::NoResponse, 35],
            ['Brightloop GmbH', 'Full-Stack Developer (Laravel/Vue)', $laravel, ApplicationSource::Indeed, ApplicationStatus::Applied, 12],
            ['Sensorik Nord', 'Backend Developer IoT Platform', $iot, ApplicationSource::Referral, ApplicationStatus::Interview, 9],
            ['Volta Devices', 'IoT Backend Developer', $iot, ApplicationSource::StepStone, ApplicationStatus::Applied, 4],
            ['Kasseler Media', 'PHP Developer', $laravel, ApplicationSource::LinkedIn, ApplicationStatus::Rejected, 25],
            ['Isar Payments', 'Senior PHP Developer', $laravel, ApplicationSource::Xing, ApplicationStatus::Applied, 2],
            ['Frankfurter Datenwerk', 'Laravel Backend Developer', $laravel, ApplicationSource::CompanySite, ApplicationStatus::Interview, 6],
        ];

        foreach ($companies as [$company, $position, $category, $source, $status, $daysAgo]) {
            $application = JobApplication::firstOrCreate(['company_name' => $company, 'position' => $position], [
                'job_category_id' => $category->id,
                'source' => $source,
                'status' => ApplicationStatus::Applied,
                'applied_at' => now()->subDays($daysAgo),
                'cover_letter_sent' => $source !== ApplicationSource::LinkedIn,
                'work_mode' => 'hybrid',
                'language' => $daysAgo % 2 ? 'de' : 'en',
            ]);

            if ($status !== ApplicationStatus::Applied && $application->wasRecentlyCreated) {
                if ($status === ApplicationStatus::Rejected && $daysAgo > 20) {
                    $application->update(['status' => ApplicationStatus::Interview]);
                }
                $application->update(['status' => $status]);
            }

            if ($status === ApplicationStatus::Rejected && $application->wasRecentlyCreated) {
                $application->notes()->create(['type' => NoteType::RejectionReason, 'content' => 'Fließendes Deutsch (C1) erforderlich']);
            }
        }

        $leads = [
            ['Backend Developer (Laravel) – hybrid Frankfurt', 'Lumen Health GmbH', 'Frankfurt am Main', 88, 'Laravel + Vue, английский рабочий, гибрид в Rhein-Main.', []],
            ['PHP Developer IoT Platform', 'Gridwise Energy', 'Remote (DE)', 81, 'PHP backend для IoT-платформы — прямое попадание в IoT-резюме.', ['Немецкий B2 желателен']],
            ['Symfony Developer', 'Altstadt Commerce', 'Mainz', 42, 'Чистый Symfony без Laravel, немецкий C1.', ['C1 Deutsch', 'Symfony only']],
            ['Junior Web Developer', 'Agentur Sieben', 'Darmstadt', 25, 'Junior-позиция, зарплата ниже цели.', ['Junior', '< 45k']],
        ];

        foreach ($leads as [$title, $company, $location, $score, $reason, $flags]) {
            JobLead::firstOrCreate(['title' => $title, 'company_name' => $company], [
                'location' => $location,
                'source' => ApplicationSource::LinkedIn,
                'score' => $score,
                'score_reason' => $reason,
                'red_flags' => $flags,
                'job_category_id' => str_contains($title, 'IoT') ? $iot->id : $laravel->id,
                'work_mode' => str_contains($location, 'Remote') ? 'remote' : 'hybrid',
                'status' => LeadStatus::New,
                'scored_at' => now(),
            ]);
        }
    }
}
