<?php

namespace App\Services;

use App\Models\Ticket;
use App\Events\SupportTimeUpdated;
use App\Enums\TicketStatusEnum;

class SupportTimeManager
{
    /**
     * Deducts a specific amount of time from the customer's daily support allowance.
     *
     * @param \App\Models\Ticket $ticket
     * @param int $seconds
     * @return int
     */
    public function deductTime(Ticket $ticket, int $seconds = 5): int
    {
        // Restriction: Only in_progress
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