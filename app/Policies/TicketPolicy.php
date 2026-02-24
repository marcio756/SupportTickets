<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

/**
 * Manages authorization logic for Ticket-related actions
 */
class TicketPolicy
{
    /**
     * Determine if the user can view the ticket
     *
     * @param User $user
     * @param Ticket $ticket
     * @return bool
     */
    public function view(User $user, Ticket $ticket): bool
    {
        // Supporters can view any ticket; Customers only their own
        return $user->isSupporter() || $ticket->customer_id === $user->id;
    }

    /**
     * Determine if the user can reply or update the ticket
     *
     * @param User $user
     * @param Ticket $ticket
     * @return bool
     */
    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->isSupporter()) {
            // Supporter must be the one assigned to the ticket
            return $ticket->assigned_to === $user->id;
        }

        // Customer can only update their own tickets
        return $ticket->customer_id === $user->id;
    }

    /**
     * Determine if the user can delete the ticket
     *
     * @param User $user
     * @param Ticket $ticket
     * @return bool
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        // Only supporters can delete tickets based on project requirements
        return $user->isSupporter();
    }
}