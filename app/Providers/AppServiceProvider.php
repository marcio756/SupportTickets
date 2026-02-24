<?php

namespace App\Providers;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Observers\TicketObserver;
use App\Observers\TicketMessageObserver;
use Illuminate\Support\ServiceProvider;

/**
 * Bootstraps application services and registers model observers 
 * to keep controllers strictly focused on HTTP responses.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        // Add any explicit container bindings here if needed in the future
    }

    /**
     * Bootstrap any application services.
     * Registers model observers to handle side-effects like notifications.
     *
     * @return void
     */
    public function boot(): void
    {
        Ticket::observe(TicketObserver::class);
        TicketMessage::observe(TicketMessageObserver::class);
    }
}