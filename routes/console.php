<?php

use Illuminate\Support\Facades\Schedule;

// Data is stored in UTC, the schedule follows local (Berlin) time.
$tz = 'Europe/Berlin';

Schedule::command('mail:sync-headers')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('leads:digest')->weekdays()->dailyAt('08:13')->timezone($tz);
Schedule::command('applications:follow-ups')->weekdays()->dailyAt('10:07')->timezone($tz);
Schedule::command('ai:strategy')->dailyAt('19:07')->timezone($tz);
