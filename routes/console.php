<?php

use Illuminate\Support\Facades\Schedule;

// Data is stored in UTC, the schedule follows local (Berlin) time.
$tz = 'Europe/Berlin';

Schedule::command('mail:sync-headers')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('leads:digest')->weekdays()->dailyAt('08:13')->timezone($tz);
Schedule::command('applications:follow-ups')->weekdays()->dailyAt('10:07')->timezone($tz);
// Skips categories without changes, so frequent runs don't burn the API quota.
Schedule::command('ai:strategy')->everyFourHours();
