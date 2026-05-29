<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule reminder check every minute
Schedule::command('bot:check-reminders')->everyMinute();

// Schedule auto-return to bot mode every 5 minutes
Schedule::job(new \App\Jobs\AutoReturnToBotJob())->everyFiveMinutes();
