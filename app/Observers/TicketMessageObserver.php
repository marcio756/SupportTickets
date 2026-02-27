<?php

namespace App\Observers;

use App\Models\TicketMessage;
use App\Notifications\TicketNotification;
use App\Services\FirebaseService;

/**
 * Observer responsible for intercepting the creation of ticket messages
 * and dispatching internal and push notifications to the appropriate parties.
 */
class TicketMessageObserver
{
    /**
     * Create a new observer instance.
     *
     * @param FirebaseService $firebaseService Service injected for push notifications.
     */
    public function __construct(private FirebaseService $firebaseService) {}

    /**
     * Notify the alternate participant when a new ticket message is dispatched.
     *
     * @param TicketMessage $message The newly created ticket message.
     * @return void
     */
    public function created(TicketMessage $message): void
    {
        $ticket = $message->ticket;
        $senderId = $message->user_id;
        
        // Resolve the intended recipient by excluding the message sender
        $recipient = ($ticket->customer_id === $senderId) ? $ticket->assignee : $ticket->customer;

        if ($recipient) {
            $title = "Nova Mensagem";
            $body = "Recebeu uma nova mensagem no ticket #{$ticket->id}";
            $payload = ['ticket_id' => (string) $ticket->id];

            // Local DB Notification
            $recipient->notify(new TicketNotification(
                $ticket, 
                $body
            ));

            // Firebase Push Notification
            $this->firebaseService->sendNotificationToUser($recipient, $title, $body, $payload);
        }
    }
}