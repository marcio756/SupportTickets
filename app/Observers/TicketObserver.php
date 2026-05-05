<?php

namespace App\Observers;

use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketNotification;
use App\Notifications\TicketCreatedDiscordNotification;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Notification;

/**
 * Observer responsible for dispatching reactive logic associated
 * with the Ticket model, such as state notifications, Firebase push,
 * and maintaining denormalized counters for extreme performance.
 */
class TicketObserver
{
    public function __construct(private FirebaseService $firebaseService) {}

    /**
     * Increment the counter when a new active ticket is directly assigned.
     */
    public function created(Ticket $ticket): void
    {
        if ($ticket->assigned_to && $this->isActiveStatus($ticket->status->value)) {
            User::where('id', $ticket->assigned_to)->increment('active_tickets_count');
        }

        // Route the notification anonymously to the custom Discord channel.
        Notification::route('discord', null)
            ->notify(new TicketCreatedDiscordNotification($ticket));
    }

    /**
     * Handles notifications and maintains the active_tickets_count integrity 
     * when statuses or assignments change.
     */
    public function updated(Ticket $ticket): void
    {
        $this->handleCounterUpdates($ticket);
        $this->handleNotifications($ticket);
    }

    /**
     * Decrement the counter if a ticket is deleted while active.
     */
    public function deleted(Ticket $ticket): void
    {
        if ($ticket->assigned_to && $this->isActiveStatus($ticket->status->value)) {
            User::where('id', $ticket->assigned_to)->decrement('active_tickets_count');
        }
    }

    /**
     * Internal logic to sync the denormalized active_tickets_count.
     * Uses direct query builder to bypass loading full User models into memory.
     */
    private function handleCounterUpdates(Ticket $ticket): void
    {
        $statusChanged = $ticket->wasChanged('status');
        $assigneeChanged = $ticket->wasChanged('assigned_to');

        if (!$statusChanged && !$assigneeChanged) {
            return;
        }

        $oldStatus = $ticket->getOriginal('status');
        // Handle Enums correctly whether they come as objects or values
        $oldStatusValue = $oldStatus instanceof TicketStatusEnum ? $oldStatus->value : $oldStatus;
        $newStatusValue = $ticket->status->value;

        $wasActive = $this->isActiveStatus($oldStatusValue);
        $isActive = $this->isActiveStatus($newStatusValue);

        $oldAssignee = $ticket->getOriginal('assigned_to');
        $newAssignee = $ticket->assigned_to;

        // Scenario 1: Same assignee, status changed from active to inactive (or vice versa)
        if ($oldAssignee === $newAssignee && $newAssignee !== null) {
            if ($wasActive && !$isActive) {
                User::where('id', $newAssignee)->decrement('active_tickets_count');
            } elseif (!$wasActive && $isActive) {
                User::where('id', $newAssignee)->increment('active_tickets_count');
            }
        } 
        // Scenario 2: Assignee changed
        elseif ($oldAssignee !== $newAssignee) {
            // Remove from old assignee if it was active
            if ($oldAssignee !== null && $wasActive) {
                User::where('id', $oldAssignee)->decrement('active_tickets_count');
            }
            // Add to new assignee if it is currently active
            if ($newAssignee !== null && $isActive) {
                User::where('id', $newAssignee)->increment('active_tickets_count');
            }
        }
    }

    /**
     * Handles dispatching notifications efficiently.
     */
    private function handleNotifications(Ticket $ticket): void
    {
        if ($ticket->wasChanged('status')) {
            $actorId = auth()->id();
            $title = "Ticket Update";
            $message = "Ticket #{$ticket->id} status was changed to: {$ticket->status->value}";
            $payload = ['ticket_id' => (string) $ticket->id];

            if (! $ticket->relationLoaded('customer')) {
                $ticket->load('customer:id,name,email');
            }

            if ($ticket->customer_id !== $actorId) {
                if ($ticket->customer) {
                    $ticket->customer->notify(new TicketNotification($ticket, $message, 'status_change'));
                    $this->firebaseService->sendNotificationToUser($ticket->customer, $title, $message, $payload);
                } elseif ($ticket->sender_email) {
                    Notification::route('mail', $ticket->sender_email)
                        ->notify(new TicketNotification($ticket, $message, 'status_change'));
                }
            }

            if ($ticket->assigned_to && $ticket->assigned_to !== $actorId) {
                if (! $ticket->relationLoaded('assignee')) {
                    $ticket->load('assignee:id,name,email');
                }
                
                if ($ticket->assignee) {
                    $ticket->assignee->notify(new TicketNotification($ticket, $message, 'status_change'));
                    $this->firebaseService->sendNotificationToUser($ticket->assignee, $title, $message, $payload);
                }
            }
        }
    }

    private function isActiveStatus(?string $status): bool
    {
        return in_array($status, [TicketStatusEnum::OPEN->value, TicketStatusEnum::IN_PROGRESS->value]);
    }
}