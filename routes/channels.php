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

/**
 * Authorize the user to listen to their own private notification channel.
 * This is required for Laravel's default notification broadcasting via $user->notify().
 * * @param User $user The currently authenticated user.
 * @param int $id The user ID from the channel name.
 * @return bool
 */
Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === (int) $id;
});