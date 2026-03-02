<?php

namespace App\Observers;

use App\Models\TicketMessage;
use App\Notifications\TicketNotification;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Notification;

class TicketMessageObserver
{
    public function __construct(private FirebaseService $firebaseService) {}

    public function created(TicketMessage $message): void
    {
        $ticket = $message->ticket;
        $senderId = $message->user_id;

        // If sent by customer (Web or Email), notify the assignee
        if ($senderId === $ticket->customer_id || ($senderId === null && $ticket->sender_email)) {
            if ($ticket->assignee) {
                $ticket->assignee->notify(new TicketNotification($ticket, $message->message));
            }
            return;
        }

        // If sent by support, notify the customer with the actual message content
        if ($ticket->customer) {
            $ticket->customer->notify(new TicketNotification($ticket, $message->message));
        } elseif ($ticket->sender_email) {
            Notification::route('mail', $ticket->sender_email)
                ->notify(new TicketNotification($ticket, $message->message));
        }
    }
}