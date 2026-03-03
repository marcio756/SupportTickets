<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Notifications\TicketNotification;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Notification;

/**
 * Observer responsible for dispatching reactive logic associated
 * with the Ticket model, such as state notifications and Firebase push.
 */
class TicketObserver
{
    /**
     * Create a new observer instance.
     *
     * @param FirebaseService $firebaseService Service injected for push notifications.
     */
    public function __construct(private FirebaseService $firebaseService) {}

    /**
     * Conditionally notifies users when the ticket status changes.
     * Ensures the actor who made the change does not receive the notification.
     *
     * @param Ticket $ticket
     * @return void
     */
    public function updated(Ticket $ticket): void
    {
        // Check if the status property was actually changed
        if ($ticket->wasChanged('status')) {
            $actorId = auth()->id();
            
            // The message to send in the notification
            $title = "Ticket Update";
            $message = "Ticket #{$ticket->id} status was changed to: {$ticket->status->value}";
            $payload = ['ticket_id' => (string) $ticket->id];

            // 1. Notify the Customer if they didn't make the change
            if ($ticket->customer_id !== $actorId) {
                if ($ticket->customer) {
                    // Registered customer account
                    $ticket->customer->notify(new TicketNotification($ticket, $message, 'status_change'));
                    $this->firebaseService->sendNotificationToUser($ticket->customer, $title, $message, $payload);
                } elseif ($ticket->sender_email) {
                    // Unregistered customer (via email)
                    Notification::route('mail', $ticket->sender_email)
                        ->notify(new TicketNotification($ticket, $message, 'status_change'));
                }
            }

            // 2. Notify the Assigned Agent if they didn't make the change
            if ($ticket->assigned_to && $ticket->assigned_to !== $actorId) {
                if ($ticket->assignee) {
                    $ticket->assignee->notify(new TicketNotification($ticket, $message, 'status_change'));
                    $this->firebaseService->sendNotificationToUser($ticket->assignee, $title, $message, $payload);
                }
            }
        }
    }
}