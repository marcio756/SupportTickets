<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Ticket;
use App\Models\User;

/**
 * Authorize users to listen to a specific ticket's real-time events.
 * Only the ticket owner (customer) and supporters can listen.
 */
Broadcast::channel('ticket.{id}', function (User $user, int $id) {
    $ticket = Ticket::find($id);
    
    if (! $ticket) {
        return false;
    }

    return $user->isSupporter() || $user->id === $ticket->customer_id;
});