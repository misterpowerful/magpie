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
