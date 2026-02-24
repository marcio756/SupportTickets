<?php

namespace App\Services;

use App\Models\Ticket;
use App\Events\SupportTimeUpdated;
use App\Enums\TicketStatusEnum;

/**
 * Service responsible for managing and calculating customer support time constraints.
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
        if ($ticket->status !== TicketStatusEnum::IN_PROGRESS) {
            return $ticket->customer->daily_support_seconds;
        }

        $customer = $ticket->customer;
        
        if ($customer->daily_support_seconds > 0) {
            $newTime = max(0, $customer->daily_support_seconds - $seconds);
            
            $customer->update([
                'daily_support_seconds' => $newTime
            ]);

            broadcast(new SupportTimeUpdated($ticket->id, $newTime));
            
            return $newTime;
        }

        return 0;
    }
}