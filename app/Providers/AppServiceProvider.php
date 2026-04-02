<?php

namespace App\Providers;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Observers\TicketObserver;
use App\Observers\TicketMessageObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        /**
         * Defines the API rate limiter used in routes/api.php
         * Limits to 60 requests per minute per user or IP address.
         */
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        Ticket::observe(TicketObserver::class);
        TicketMessage::observe(TicketMessageObserver::class);
    }
}