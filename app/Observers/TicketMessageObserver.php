<?php

namespace App\Observers;

use App\Models\TicketMessage;
use App\Notifications\TicketNotification;

/**
 * Observer responsible for intercepting the creation of ticket messages
 * and dispatching notifications to the appropriate parties, enforcing SRP.
 */
class TicketMessageObserver
{
    /**
     * Notify the alternate participant when a new ticket message is dispatched.
     *
     * @param TicketMessage $message The newly created ticket message.
     * @return void
     */
    public function created(TicketMessage $message): void
    {
        $ticket = $message->ticket;
        $senderId = $message->user_id;
        
        // Resolve the intended recipient by excluding the message sender
        $recipient = ($ticket->customer_id === $senderId) ? $ticket->assignee : $ticket->customer;

        if ($recipient) {
            // Correctly instantiate the TicketNotification with (Ticket $ticket, string $message)
            $recipient->notify(new TicketNotification(
                $ticket, 
                "Nova mensagem no ticket #{$ticket->id}"
            ));
        }
    }
}