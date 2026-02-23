<?php

namespace App\Observers;

use App\Models\TicketMessage;
use App\Notifications\TicketNotification;

class TicketMessageObserver
{
    /**
     * Notify the other participant when a new message is sent.
     */
    public function created(TicketMessage $message): void
    {
        $ticket = $message->ticket;
        $senderId = $message->user_id;
        
        // Recipient is the person who is NOT the sender
        $recipient = ($ticket->user_id === $senderId) ? $ticket->agent : $ticket->user;

        if ($recipient) {
            $recipient->notify(new TicketNotification([
                'ticket_id' => $ticket->id,
                'title'     => 'Nova Mensagem',
                'message'   => "Nova mensagem no ticket #{$ticket->id}",
                'type'      => 'new_message'
            ]));
        }
    }
}