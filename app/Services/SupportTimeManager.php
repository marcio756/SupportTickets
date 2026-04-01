<?php

namespace App\Services;

use App\Models\Ticket;
use App\Events\SupportTimeUpdated;
use App\Enums\TicketStatusEnum;

/**
 * Service responsible for managing and calculating customer support time constraints.
 * * Architect Note: Optimized for high-concurrency using atomic database operations
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
        // Architect Note: We explicitly compare against the Enum's value to prevent 
        // strict type mismatch errors and ensure we only process active workflows.
        if ($ticket->status !== TicketStatusEnum::IN_PROGRESS->value) {
            return $ticket->customer->daily_support_seconds ?? 0;
        }

        $customer = $ticket->customer;
        
        if ($customer && $customer->daily_support_seconds > 0) {
            
            $deduction = min($seconds, $customer->daily_support_seconds);
            
            // Architect Note: Replaced the standard $customer->update() with an atomic decrement().
            // In a highly concurrent system, standard updates create Race Conditions where simultaneous 
            // requests overwrite each other's counts. decrement() delegates the math directly to the 
            // database engine (PostgreSQL/MySQL), ensuring ultra-fast and accurate execution.
            $customer->decrement('daily_support_seconds', $deduction);
            
            // Reload the fresh value directly from the database to ensure the WebSocket 
            // broadcasts the absolute source of truth.
            $newTime = $customer->fresh()->daily_support_seconds;

            broadcast(new SupportTimeUpdated($ticket->id, $newTime));
            
            return $newTime;
        }

        return 0;
    }
}