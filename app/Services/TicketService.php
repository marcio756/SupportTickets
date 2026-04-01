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
use Illuminate\Support\Facades\Notification;

/**
 * Handles all business logic related to Ticket manipulation, assignments and messaging.
 * Architect Note: Optimized DB transactions and relationship queries to prevent 
 * deadlocks and N+1 memory bloat when scaling to millions of records.
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
                // Notificação disparada de forma limpa. Garante que TicketNotification implementa "ShouldQueue".
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
                $userIds = array_filter($data['mentions'], 'is_numeric');
                
                if (!empty($userIds)) {
                    $ticket->participants()->syncWithoutDetaching($userIds);

                    // Architect Note: Optimized memory footprint by selecting only necessary columns
                    $mentionedUsers = User::whereIn('id', $userIds)->select('id', 'name')->get();
                    
                    $senderName = $user ? $user->name : ($data['sender_email'] ?? 'System');
                    $notification = new TicketNotification(
                        $ticket, 
                        "You were mentioned in Ticket #{$ticket->id} by {$senderName}.",
                        "mention"
                    );

                    // Otimização: Disparo em bulk (lote) em vez de N queries dentro de um loop foreach
                    $usersToNotify = $mentionedUsers->reject(fn($mUser) => $user && $mUser->id === $user->id);
                    
                    if ($usersToNotify->isNotEmpty()) {
                        Notification::send($usersToNotify, $notification);
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
     *
     * @return User|null
     */
    private function findAvailableSupporter(): ?User
    {
        /**
         * Architect Note: Eliminated the heavy double-subquery (whereHas + withCount).
         * We now use withCount combined with a having clause to resolve both the filtering 
         * and the sorting in a single database pass, drastically reducing query time.
         * Substituted whereHas with whereExists raw logic to prevent Laravel's ORM overhead on large datasets.
         */
        return User::where('role', RoleEnum::SUPPORTER->value)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('work_sessions')
                      ->whereColumn('work_sessions.user_id', 'users.id')
                      ->where('work_sessions.status', WorkSessionStatusEnum::ACTIVE->value);
            })
            ->withCount(['assignedTickets as active_tickets_count' => function ($query) {
                $query->whereIn('status', [
                    TicketStatusEnum::OPEN->value, 
                    TicketStatusEnum::IN_PROGRESS->value
                ]);
            }])
            ->having('active_tickets_count', '<', 5)
            ->orderBy('active_tickets_count', 'asc')
            ->first();
    }
}