<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Register each day's recurring todos just after midnight. Runs synchronously
// in the scheduler, so no queue worker is required for this to be reliable.
Schedule::command('recurrences:materialize')->dailyAt('00:05');
