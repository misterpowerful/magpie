<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Spec section 2 "Schedule": 2am Mon/Wed/Fri so the Claude Pro usage
// window resets by 7am, plus a 7:05am catch-up if the 2am run paused.
// ->timezone() keeps this correct across DST without manual re-pinning
// (unlike raw crontab UTC offsets — see magpie 1.0's cron comment).
Schedule::command('magpie:run --trigger=schedule')
    ->cron('0 2 * * 1,3,5')
    ->timezone('America/Chicago');

Schedule::command('magpie:catch-up')
    ->cron('5 7 * * *')
    ->timezone('America/Chicago');

// Standalone, more-frequent application-confirmation scan — decoupled
// from the twice/thrice-weekly pipeline run above so a "thank you for
// applying" email doesn't sit unmatched for days. No LLM calls (regex
// only) and no owner-specific wall-clock target, so unlike the two
// schedules above this doesn't need a fixed local time or ->timezone().
Schedule::command('magpie:check-confirmations')
    ->cron('0 */4 * * *');

// Picks up the dashboard's "Pull from email" button: the web process
// can't run the claude CLI, so it files a request and this does the pull.
Schedule::command('magpie:run-requested-email-pull')
    ->everyMinute()
    ->withoutOverlapping();
