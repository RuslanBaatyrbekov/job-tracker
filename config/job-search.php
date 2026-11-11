<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Candidate profile
    |--------------------------------------------------------------------------
    |
    | Used by the AI when it scores new vacancies (leads). Describe what a good
    | match is for you — the clearer the rules, the better the scoring.
    |
    */

    'profile' => env('JOB_SEARCH_PROFILE', <<<'TEXT'
Backend developer, PHP/Laravel, 3.5+ years of commercial experience (MySQL, REST APIs, Docker, CI/CD, Vue.js, WebSockets).
Hobby/side experience with embedded & IoT (ESP32, Arduino, PlatformIO, BLE, GPS/sensors).
English B2 (working language). German: integration course, B1 in progress.
Location: Offenbach am Main (Frankfurt Rhine-Main). Wants hybrid in Rhine-Main or remote within Germany.
Long-term preference: Munich / Bavaria — relocation only for a strong offer.
Target salary: ~50-55k EUR.
TEXT),

    'scoring_rules' => env('JOB_SEARCH_SCORING_RULES', <<<'TEXT'
- Strong plus: Laravel in the stack, PHP 8, backend/API focus, English as working language.
- Plus: Vue.js, Docker, queues, high-load, IoT / connected devices, product companies.
- Neutral: German B1-B2 "nice to have" — do NOT reject only because of German, many such postings still invite to HR screening.
- Weak match: pure Symfony without Laravel, junior-titled roles, German C1+ as a hard requirement, on-site only far from Rhine-Main.
- Red flags: unpaid trial work, "freelance only", agencies with vague descriptions, salary clearly below 45k.
TEXT),

    /*
    |--------------------------------------------------------------------------
    | Language of AI answers shown to you (advice, prep packs, digests)
    |--------------------------------------------------------------------------
    */

    'ai_language' => env('AI_RESPONSE_LANGUAGE', 'Russian'),

    /*
    |--------------------------------------------------------------------------
    | Leads
    |--------------------------------------------------------------------------
    */

    'leads' => [
        // Minimum score (0-100) for a lead to appear in the Telegram digest.
        'digest_min_score' => (int) env('LEADS_DIGEST_MIN_SCORE', 60),
        'digest_limit' => (int) env('LEADS_DIGEST_LIMIT', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Follow-ups
    |--------------------------------------------------------------------------
    */

    'follow_up' => [
        // Remind to send a follow-up after N days without any answer.
        'remind_after_days' => (int) env('FOLLOW_UP_AFTER_DAYS', 10),
        // Mark as "no response" (ghosted) after N days.
        'ghosted_after_days' => (int) env('GHOSTED_AFTER_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Weekly goal shown on the dashboard
    |--------------------------------------------------------------------------
    */

    'weekly_goal' => (int) env('WEEKLY_APPLICATIONS_GOAL', 10),

];
