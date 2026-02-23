<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Observers\TicketObserver;
use App\Observers\TicketMessageObserver;

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
        // Observa alterações de status no Ticket
        Ticket::observe(TicketObserver::class);
        
        // Observa novas mensagens
        TicketMessage::observe(TicketMessageObserver::class);
    }
}