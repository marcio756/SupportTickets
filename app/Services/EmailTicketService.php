<?php

namespace App\Services;

use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Support\Facades\Log;

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

        // Halt execution if the sender is not registered. 
        // This prevents unauthorized entities or automated spam from bloating the database.
        if (!$user) {
            Log::warning('Support email ignored: Sender not found in records.', [
                'email' => $emailData['from_email']
            ]);
            return null;
        }

        $ticketId = $this->extractTicketIdFromSubject($emailData['subject']);

        if ($ticketId) {
            return $this->appendMessageToTicket($ticketId, $user->id, $emailData['body']);
        }

        return $this->createNewTicket($user->id, $emailData['subject'], $emailData['body']);
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
     *
     * @param int $userId
     * @param string $subject
     * @param string $body
     * @return \App\Models\Ticket
     */
    private function createNewTicket(int $userId, string $subject, string $body): Ticket
    {
        $ticket = Ticket::create([
            'customer_id' => $userId, // Alterado para corresponder à tua migração
            'title'       => $subject,
            // Status may be handled by default database values or an observer,
            // but normally we would start it as 'open' or 'pending'.
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $userId, // A migração de mensagens usa user_id
            'message'   => $body,
        ]);

        return $ticket;
    }

    /**
     * Attaches a sequential message to an already existing ticket thread.
     *
     * @param int $ticketId
     * @param int $userId
     * @param string $body
     * @return \App\Models\Ticket|null
     */
    private function appendMessageToTicket(int $ticketId, int $userId, string $body): ?Ticket
    {
        $ticket = Ticket::find($ticketId);

        // Fail gracefully if the parsed ID does not map to a real ticket.
        if (!$ticket) {
            Log::warning('Support email reply failed: Ticket ID does not exist.', [
                'ticket_id' => $ticketId,
                'user_id'   => $userId
            ]);
            return null; 
        }

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $userId,
            'message'   => $body,
        ]);

        // Triggers timestamps update so the ticket registers recent activity
        $ticket->touch();

        return $ticket;
    }
}