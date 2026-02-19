<?php

namespace App\Services;

use App\Models\Ticket;
use App\Events\SupportTimeUpdated;

class SupportTimeManager
{
    /**
     * Deducts a specific amount of time from the customer's daily support allowance.
     * Broadcasts an event if the deduction is successful.
     *
     * @param \App\Models\Ticket $ticket The active ticket being viewed.
     * @param int $seconds The amount of seconds to deduct.
     * @return int The remaining seconds in the customer's daily allowance.
     */
    public function deductTime(Ticket $ticket, int $seconds = 5): int
    {
        // Do not deduct time if the ticket is already closed or resolved
        if ($ticket->status->value !== 'open') {
            return $ticket->customer->daily_support_seconds;
        }

        $customer = $ticket->customer;
        
        // Ensure we do not deduct below zero
        if ($customer->daily_support_seconds > 0) {
            $newTime = max(0, $customer->daily_support_seconds - $seconds);
            
            $customer->update([
                'daily_support_seconds' => $newTime
            ]);

            // Notify frontend via WebSockets
            broadcast(new SupportTimeUpdated($ticket->id, $newTime));
            
            return $newTime;
        }

        return 0;
    }
}