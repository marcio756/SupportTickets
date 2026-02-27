<?php

namespace App\Services;

use App\Enums\TicketStatusEnum;
use App\Events\TicketMessageCreated;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Service responsible for handling core Ticket business logic.
 * Ensures DRY and SRP by providing a unified workflow for both Web and API controllers.
 */
class TicketService
{
    /**
     * @param AttachmentService $attachmentService
     */
    public function __construct(
        protected AttachmentService $attachmentService
    ) {}

    /**
     * Creates a new ticket and its initial message.
     * Attach tags immediately if provided.
     *
     * @param User $user
     * @param array $data
     * @param UploadedFile|null $attachment
     * @return Ticket
     */
    public function createTicket(User $user, array $data, ?UploadedFile $attachment): Ticket
    {
        $isSupporter = $user->isSupporter();
        $customerId = $isSupporter && !empty($data['customer_id']) ? $data['customer_id'] : $user->id;

        $ticket = Ticket::create([
            'customer_id' => $customerId,
            'title' => $data['title'],
            'status' => TicketStatusEnum::OPEN,
            'assigned_to' => $isSupporter ? $user->id : null,
        ]);

        if (isset($data['tags']) && is_array($data['tags'])) {
            $ticket->tags()->sync($data['tags']);
        }

        $attachmentPath = $attachment ? $this->attachmentService->store($attachment) : null;

        $ticket->messages()->create([
            'user_id' => $user->id,
            'message' => $data['message'] ?? '',
            'attachment_path' => $attachmentPath,
        ]);

        return $ticket;
    }

    /**
     * Assigns a ticket to the authenticated supporter.
     *
     * @param User $user
     * @param Ticket $ticket
     * @return Ticket
     */
    public function assignTicket(User $user, Ticket $ticket): Ticket
    {
        if (! $user->isSupporter()) {
            abort(403, 'Only supporters can claim tickets.');
        }

        $ticket->update([
            'assigned_to' => $user->id,
        ]);

        return $ticket;
    }

    /**
     * Updates the status of an existing ticket and logs the change.
     *
     * @param User $user
     * @param Ticket $ticket
     * @param string $newStatus
     * @return Ticket
     */
    public function updateStatus(User $user, Ticket $ticket, string $newStatus): Ticket
    {
        if ($user->isSupporter() && $ticket->assigned_to !== $user->id) {
            abort(403, 'You must be assigned to this ticket to change its status.');
        } elseif (! $user->isSupporter() && $ticket->customer_id !== $user->id) {
            abort(403, 'Unauthorized access.');
        }

        if (! $user->isSupporter() && $newStatus !== TicketStatusEnum::RESOLVED->value) {
            abort(403, 'Customers can only mark tickets as resolved.');
        }

        $ticket->update(['status' => $newStatus]);

        $message = $ticket->messages()->create([
            'user_id' => $user->id,
            'message' => "🔄 O estado do ticket foi alterado para: " . strtoupper($newStatus),
        ]);

        $message->load('sender');
        broadcast(new TicketMessageCreated($message));

        return $ticket;
    }

    /**
     * Appends a new message to an ongoing ticket, validating constraints.
     *
     * @param User $user
     * @param Ticket $ticket
     * @param array $data
     * @param UploadedFile|null $attachment
     * @return TicketMessage
     */
    public function sendMessage(User $user, Ticket $ticket, array $data, ?UploadedFile $attachment): TicketMessage
    {
        if ($ticket->status !== TicketStatusEnum::IN_PROGRESS) {
            abort(403, 'The ticket must be "In Progress" to send messages.');
        }

        if ($user->isSupporter() && $ticket->assigned_to !== $user->id) {
            abort(403, 'You must claim this ticket before replying.');
        } elseif (! $user->isSupporter() && $ticket->customer_id !== $user->id) {
            abort(403, 'Unauthorized access.');
        }

        if (! $user->isSupporter() && $user->daily_support_seconds <= 0) {
            abort(403, 'No support time available.');
        }

        $attachmentPath = $attachment ? $this->attachmentService->store($attachment) : null;

        $message = $ticket->messages()->create([
            'user_id' => $user->id,
            'message' => $data['message'] ?? '',
            'attachment_path' => $attachmentPath,
        ]);

        $message->load('sender');
        broadcast(new TicketMessageCreated($message));

        return $message;
    }
}