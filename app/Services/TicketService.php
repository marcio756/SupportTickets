<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Enums\TicketStatusEnum;
use App\Enums\WorkSessionStatusEnum;
use App\Events\TicketMessageCreated;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\TicketNotification;
use Illuminate\Support\Facades\DB;

/**
 * Handles all business logic related to Ticket manipulation, assignments and messaging.
 */
class TicketService
{
    protected AttachmentService $attachmentService;

    public function __construct(AttachmentService $attachmentService)
    {
        $this->attachmentService = $attachmentService;
    }

    public function createTicket(?User $creator, array $data, $attachments = null): Ticket
    {
        return DB::transaction(function () use ($creator, $data, $attachments) {
            $ticket = new Ticket();
            
            if (isset($data['title'])) $ticket->title = $data['title'];
            if (isset($data['description'])) $ticket->description = $data['description'];
            if (isset($data['priority'])) $ticket->priority = $data['priority'];
            
            $ticket->status = TicketStatusEnum::OPEN->value;
            
            // Assign the creator ID to the correct database column 'customer_id'
            $ticket->customer_id = $creator ? $creator->id : null;
            
            $ticket->source = $data['source'] ?? 'web';
            
            if (isset($data['sender_email'])) {
                $ticket->sender_email = $data['sender_email'];
            }

            $availableSupporter = $this->findAvailableSupporter();
            
            if ($availableSupporter) {
                $ticket->assigned_to = $availableSupporter->id;
            }

            $ticket->save();

            if (isset($data['tags']) && is_array($data['tags'])) {
                $ticket->tags()->sync($data['tags']);
            }

            if ($attachments) {
                $this->attachmentService->processAttachments($attachments, clone $ticket);
            } elseif (isset($data['attachments']) && is_array($data['attachments'])) {
                $this->attachmentService->processAttachments($data['attachments'], clone $ticket);
            }

            if ($availableSupporter) {
                $availableSupporter->notify(new TicketNotification(
                    $ticket, 
                    "A new ticket #{$ticket->id} has been automatically assigned to you.",
                    "assignment"
                ));
            }

            return $ticket;
        });
    }

    public function sendMessage(?User $user, Ticket $ticket, array $data, $attachments = null): TicketMessage
    {
        // Business Rule: Block customers with no available support time
        if ($user && $user->role === RoleEnum::CUSTOMER->value) {
            $supportTimeManager = app(\App\Services\SupportTimeManager::class);
            if (!$supportTimeManager->hasAvailableTime($user)) {
                abort(403, 'Insufficient support time.');
            }
        }

        return DB::transaction(function () use ($user, $ticket, $data, $attachments) {
            $message = new TicketMessage();
            $message->ticket_id = $ticket->id;
            $message->message = $data['message'];
            
            if ($user) {
                $message->user_id = $user->id;
            } elseif (isset($data['sender_email'])) {
                $message->sender_email = $data['sender_email'];
            }
            
            $message->save();

            if ($attachments) {
                $this->attachmentService->processAttachments($attachments, clone $message);
            } elseif (isset($data['attachments']) && is_array($data['attachments'])) {
                $this->attachmentService->processAttachments($data['attachments'], clone $message);
            }

            // Notification Logic for Mentions
            if (!empty($data['mentions'])) {
                // Filter only numeric user IDs to prevent crashes when processing raw emails
                $userIds = array_filter($data['mentions'], 'is_numeric');
                
                if (!empty($userIds)) {
                    // GRANT PERMISSION: Explicitly attach mentioned users to the participants pivot table
                    $ticket->participants()->syncWithoutDetaching($userIds);

                    $mentionedUsers = User::whereIn('id', $userIds)->get();
                    foreach ($mentionedUsers as $mentionedUser) {
                        // Prevent sending notification to the sender if they mention themselves
                        if ($user && $mentionedUser->id === $user->id) continue;
                        
                        $senderName = $user ? $user->name : ($data['sender_email'] ?? 'System');
                        
                        $mentionedUser->notify(new TicketNotification(
                            $ticket, 
                            "You were mentioned in Ticket #{$ticket->id} by {$senderName}.",
                            "mention"
                        ));
                    }
                }
            }

            broadcast(new TicketMessageCreated($message))->toOthers();

            return $message;
        });
    }

    public function updateStatus(User $user, Ticket $ticket, string $status): Ticket
    {
        $ticket->status = $status;
        $ticket->save();
        return $ticket;
    }

    /**
     * Assigns a ticket to a supporter. If no target supporter is specified, 
     * it assigns the ticket to the acting user (self-claim).
     *
     * @param User $user The user performing the action.
     * @param Ticket $ticket The ticket to be assigned.
     * @param User|null $supporter The target supporter (optional).
     * @return Ticket
     */
    public function assignTicket(User $user, Ticket $ticket, ?User $supporter = null): Ticket
    {
        $targetUser = $supporter ?? $user;
        $ticket->assigned_to = $targetUser->id;
        $ticket->save();
        return $ticket;
    }

    /**
     * Retrieves an available supporter prioritizing the one with the least active tickets.
     * Uses database-agnostic constraints to guarantee correct filtering across any SQL driver.
     *
     * @return User|null
     */
    private function findAvailableSupporter(): ?User
    {
        return User::where('role', RoleEnum::SUPPORTER->value)
            ->whereHas('workSessions', function ($query) {
                $query->where('status', WorkSessionStatusEnum::ACTIVE->value);
            })
            // Filters out any supporter that already has 5 or more active tickets.
            // This natively executes a robust WHERE EXISTS statement compatible with strict engines like SQLite.
            ->whereHas('assignedTickets', function ($query) {
                $query->whereIn('status', [
                    TicketStatusEnum::OPEN->value, 
                    TicketStatusEnum::IN_PROGRESS->value
                ]);
            }, '<', 5)
            // Adds the count projection merely to facilitate ascending sorting by workload.
            ->withCount(['assignedTickets as active_tickets_count' => function ($query) {
                $query->whereIn('status', [
                    TicketStatusEnum::OPEN->value, 
                    TicketStatusEnum::IN_PROGRESS->value
                ]);
            }])
            ->orderBy('active_tickets_count', 'asc')
            ->first();
    }
}