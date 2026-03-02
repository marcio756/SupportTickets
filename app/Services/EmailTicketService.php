<?php

namespace App\Services;

use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\TicketCreatedAutoReply;

/**
 * Service responsible for processing incoming support emails.
 * It handles client matching, thread identification via subject parsing,
 * and the creation of database entities.
 */
class EmailTicketService
{
    /**
     * Processes an incoming email payload to either instantiate a brand new ticket
     * or append a response to an ongoing conversation.
     *
     * @param array $emailData
     * @return \App\Models\Ticket|null
     */
    public function processEmail(array $emailData): ?Ticket
    {
        $user = User::where('email', $emailData['from_email'])->first();
        $userId = $user ? $user->id : null;

        $ticketId = $this->extractTicketIdFromSubject($emailData['subject']);

        if ($ticketId) {
            return $this->appendMessageToTicket($ticketId, $userId, $emailData['body'], $emailData['from_email']);
        }

        return $this->createNewTicket($userId, $emailData['subject'], $emailData['body'], $emailData['from_email']);
    }

    /**
     * Parses the email subject line to locate an existing Ticket ID.
     * Looks for a specific bracketed pattern to maintain conversation consistency.
     *
     * @param string $subject
     * @return int|null
     */
    private function extractTicketIdFromSubject(string $subject): ?int
    {
        // Regex looks for digits enclosed in standard square brackets, e.g., "[1234]"
        if (preg_match('/\[(\d+)\]/', $subject, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Bootstraps a new ticket entity and logs its foundational message.
     * Supports unregistered users by storing their email address.
     *
     * @param int|null $userId
     * @param string $subject
     * @param string $body
     * @param string $senderEmail
     * @return \App\Models\Ticket
     */
    private function createNewTicket(?int $userId, string $subject, string $body, string $senderEmail): Ticket
    {
        $ticket = Ticket::create([
            'customer_id'  => $userId,
            'sender_email' => $senderEmail,
            'title'        => $subject,
            'source'       => 'email', 
        ]);

        TicketMessage::create([
            'ticket_id'    => $ticket->id,
            'user_id'      => $userId,
            'sender_email' => $userId ? null : $senderEmail,
            'message'      => $body,
        ]);

        // Disparar o envio do email de auto-resposta para o remetente (registado ou não)
        Mail::to($senderEmail)->send(new TicketCreatedAutoReply($ticket));

        return $ticket;
    }

    /**
     * Attaches a sequential message to an already existing ticket thread.
     *
     * @param int $ticketId
     * @param int|null $userId
     * @param string $body
     * @param string $senderEmail
     * @return \App\Models\Ticket|null
     */
    private function appendMessageToTicket(int $ticketId, ?int $userId, string $body, string $senderEmail): ?Ticket
    {
        $ticket = Ticket::find($ticketId);

        // Fail gracefully if the parsed ID does not map to a real ticket.
        if (!$ticket) {
            Log::warning('Support email reply failed: Ticket ID does not exist.', [
                'ticket_id' => $ticketId,
                'user_id'   => $userId,
                'email'     => $senderEmail
            ]);
            return null; 
        }

        TicketMessage::create([
            'ticket_id'    => $ticket->id,
            'user_id'      => $userId,
            'sender_email' => $userId ? null : $senderEmail,
            'message'      => $body,
        ]);

        // Triggers timestamps update so the ticket registers recent activity
        $ticket->touch();

        return $ticket;
    }
}