<?php

namespace App\Providers;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Observers\TicketObserver;
use App\Observers\TicketMessageObserver;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        /**
         * Register model observers for the notification system.
         * These observers handle the logic for status changes and new messages.
         */
        Ticket::observe(TicketObserver::class);
        TicketMessage::observe(TicketMessageObserver::class);
    }
}