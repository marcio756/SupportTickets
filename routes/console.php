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

/**
 * Mailbox Watcher: Retrieves incoming support emails and processes them into tickets.
 */
Schedule::command('app:fetch-support-emails')->everyMinute();

/**
 * Ticket Automation: Scans for tickets inactive for 72h and automatically closes them.
 * Executed hourly to ensure tickets are closed closely to the 72h mark.
 */
Schedule::command('app:close-inactive-tickets')->hourly();