<?php

namespace App\Observers;

use App\Models\TicketMessage;
use App\Notifications\TicketNotification;

class TicketMessageObserver
{
    /**
     * Notify the alternate participant when a new ticket message is dispatched.
     *
     * @param TicketMessage $message
     * @return void
     */
    public function created(TicketMessage $message): void
    {
        $ticket = $message->ticket;
        $senderId = $message->user_id;
        
        // Resolve the intended recipient by excluding the message sender
        // Alterado para corresponder aos nomes das colunas e relações do teu Modelo Ticket
        $recipient = ($ticket->customer_id === $senderId) ? $ticket->assignee : $ticket->customer;

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