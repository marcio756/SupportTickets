<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

/**
 * Manages authorization logic for Ticket-related actions.
 */
class TicketPolicy
{
    /**
     * Intercepts all policy checks before they are executed.
     * Grants superuser privileges to Administrators, bypassing strict assignment logic.
     *
     * @param User $user
     * @param string $ability
     * @return bool|null
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Return null to fall back to specific ability methods for non-admins
        return null;
    }

    /**
     * Determine if the user can view the ticket.
     *
     * @param User $user
     * @param Ticket $ticket
     * @return bool
     */
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->isSupporter() || $ticket->customer_id === $user->id;
    }

    /**
     * Determine if the user can reply or update the ticket.
     *
     * @param User $user
     * @param Ticket $ticket
     * @return bool
     */
    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->isSupporter()) {
            // Supporter must be the one assigned OR explicitly added as a participant via mention
            if ($ticket->assigned_to === $user->id) {
                return true;
            }
            
            return $ticket->participants()->where('users.id', $user->id)->exists();
        }

        // Customer can only update their own tickets
        return $ticket->customer_id === $user->id;
    }

    /**
     * Determine if the user can delete the ticket.
     *
     * @param User $user
     * @param Ticket $ticket
     * @return bool
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        // Supporter rules applied (Admin is caught earlier by the before() interceptor)
        return $user->isSupporter();
    }
}