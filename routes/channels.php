<?php

use App\Models\User;
use App\Models\Ticket;
use Illuminate\Support\Facades\Broadcast;

/**
 * Register default broadcasting authorization rules.
 */
// Correção: Removido o type-hint 'int' para evitar falhas de type casting na injeção via WebSockets
Broadcast::channel('App.Models.User.{id}', function (User $user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Secure the private ticket channel.
 * Only the customer who owns the ticket OR an authorized supporter can listen to it.
 */
Broadcast::channel('ticket.{ticketId}', function (User $user, $ticketId) {
    // Supporters have global access to ticket channels
    if ($user->isSupporter()) {
        return true;
    }

    // Customers can only access their own ticket channels
    $ticket = Ticket::find($ticketId);
    return $ticket !== null && (int) $ticket->customer_id === (int) $user->id;
});