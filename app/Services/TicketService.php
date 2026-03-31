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
 * Architect Note: Highly optimized for scale. Auto-assignment relies on strictly 
 * indexed, denormalized database columns (is_online, active_tickets_count) to provide O(1) execution time.
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

            if (!empty($data['mentions'])) {
                $userIds = array_filter($data['mentions'], 'is_numeric');
                
                if (!empty($userIds)) {
                    $ticket->participants()->syncWithoutDetaching($userIds);

                    $mentionedUsers = User::whereIn('id', $userIds)->select('id', 'name')->get();
                    
                    foreach ($mentionedUsers as $mentionedUser) {
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

    public function assignTicket(User $user, Ticket $ticket, ?User $supporter = null): Ticket
    {
        $targetUser = $supporter ?? $user;
        $ticket->assigned_to = $targetUser->id;
        $ticket->save();
        return $ticket;
    }

    /**
     * Retrieves an available supporter prioritizing the one with the least active tickets.
     * Architect Note: Transformed from an expensive multi-table scan into a lightning-fast
     * single table index scan utilizing the denormalized `is_online` and `active_tickets_count` fields.
     *
     * @return User|null
     */
    private function findAvailableSupporter(): ?User
    {
        return User::where('role', RoleEnum::SUPPORTER->value)
            ->where('is_online', true)
            ->where('active_tickets_count', '<', 5)
            ->orderBy('active_tickets_count', 'asc')
            ->first();
    }
}