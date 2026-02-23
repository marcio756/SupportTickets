<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Notifications\TicketNotification;

class TicketObserver
{
    /**
     * Notify users when the ticket status changes.
     */
    public function updated(Ticket $ticket): void
    {
        if ($ticket->wasChanged('status')) {
            $actorId = auth()->id();
            $notificationData = [
                'ticket_id' => $ticket->id,
                'title'     => 'Status Alterado',
                'message'   => "O ticket #{$ticket->id} mudou para {$ticket->status->value}",
                'type'      => 'status_change'
            ];

            // Notify Customer if they didn't make the change
            if ($ticket->user_id !== $actorId) {
                $ticket->user->notify(new TicketNotification($notificationData));
            }

            // Notify Support/Agent if they didn't make the change
            if ($ticket->agent_id && $ticket->agent_id !== $actorId) {
                $ticket->agent->notify(new TicketNotification($notificationData));
            }
        }
    }
}