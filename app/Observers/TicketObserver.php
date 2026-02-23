<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Notifications\TicketNotification;

class TicketObserver
{
    /**
     * Notify users conditionally when the ticket status changes.
     * Ensures the actor who made the change does not receive a notification.
     *
     * @param Ticket $ticket
     * @return void
     */
    public function updated(Ticket $ticket): void
    {
        // Verifica se a propriedade status foi efetivamente alterada
        if ($ticket->wasChanged('status')) {
            $actorId = auth()->id();
            
            $notificationData = [
                'ticket_id' => $ticket->id,
                'title'     => 'Status Alterado',
                'message'   => "O ticket #{$ticket->id} mudou para {$ticket->status->value}",
                'type'      => 'status_change'
            ];

            // Notify Customer if they aren't the ones changing the state
            // Alterado de $ticket->user_id para $ticket->customer_id
            if ($ticket->customer_id !== $actorId) {
                // Alterado de $ticket->user para $ticket->customer
                if ($ticket->customer) {
                    $ticket->customer->notify(new TicketNotification($notificationData));
                }
            }

            // Notify Support/Agent if they aren't the ones changing the state
            // Alterado de $ticket->agent_id para $ticket->assigned_to
            if ($ticket->assigned_to && $ticket->assigned_to !== $actorId) {
                // Alterado de $ticket->agent para $ticket->assignee
                if ($ticket->assignee) {
                    $ticket->assignee->notify(new TicketNotification($notificationData));
                }
            }
        }
    }
}