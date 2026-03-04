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
            $ticket->created_by = $creator ? $creator->id : null;
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
                $availableSupporter->notify(new TicketNotification($ticket, 'Foi-lhe atribuído um novo ticket automaticamente.'));
            }

            return $ticket;
        });
    }

    public function sendMessage(?User $user, Ticket $ticket, array $data, $attachments = null): TicketMessage
    {
        // Regra de Negócio: Bloquear clientes sem tempo de suporte
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

    public function assignTicket(User $user, Ticket $ticket, User $supporter): Ticket
    {
        $ticket->assigned_to = $supporter->id;
        $ticket->save();
        return $ticket;
    }

    private function findAvailableSupporter(): ?User
    {
        return User::where('role', RoleEnum::SUPPORTER->value)
            ->whereHas('workSessions', function ($query) {
                $query->where('status', WorkSessionStatusEnum::ACTIVE->value);
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