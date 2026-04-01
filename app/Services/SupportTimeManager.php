<?php

namespace App\Services;

use App\Models\Ticket;
use App\Events\SupportTimeUpdated;
use App\Enums\TicketStatusEnum;

/**
 * Service responsible for managing and calculating customer support time constraints.
 * Architect Note: Optimized for high-concurrency using atomic database operations
 * to prevent race conditions and reduce database load during continuous heartbeats.
 */
class SupportTimeManager
{
    /**
     * Deducts a specific amount of time from the customer's daily support allowance
     * and broadcasts the update to connected clients via WebSockets.
     *
     * @param \App\Models\Ticket $ticket The ticket triggering the deduction.
     * @param int $seconds The number of seconds to deduct.
     * @return int The updated remaining support seconds.
     */
    public function deductTime(Ticket $ticket, int $seconds = 5): int
    {
        // Enforce time deduction constraints to active tickets only
        // Architect Note: Garantido que comparamos com o valor do Enum (->value)
        if ($ticket->status !== TicketStatusEnum::IN_PROGRESS->value) {
            return $ticket->customer->daily_support_seconds ?? 0;
        }

        $customer = $ticket->customer;
        
        if ($customer && $customer->daily_support_seconds > 0) {
            
            $deduction = min($seconds, $customer->daily_support_seconds);
            
            // Architect Note: Substituído o $customer->update() por um decrement() atómico.
            // Num sistema com alta concorrência, fazer "update()" clássico gera "Race Conditions" 
            // (onde 2 pedidos ao mesmo tempo anulam a contagem um do outro). O decrement() delega 
            // a matemática diretamente para o motor da base de dados (PostgreSQL/MySQL), sendo ultra rápido.
            $customer->decrement('daily_support_seconds', $deduction);
            
            // Recarregamos o valor limpo da base de dados para o Web-Socket
            $newTime = $customer->fresh()->daily_support_seconds;

            broadcast(new SupportTimeUpdated($ticket->id, $newTime));
            
            return $newTime;
        }

        return 0;
    }
}