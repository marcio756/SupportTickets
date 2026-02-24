<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/**
 * Default Laravel inspire command.
 */
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/**
 * Core Business Logic: Reset customer support time allowance daily at midnight.
 * Ensure the server cron is configured to run `php artisan schedule:run` every minute.
 */
Schedule::command('app:reset-customer-chat-time')->dailyAt('00:00');