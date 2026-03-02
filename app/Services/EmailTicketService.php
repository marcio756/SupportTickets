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
 * Handles client matching, thread identification, and history stripping.
 */
class EmailTicketService
{
    /**
     * Processes an incoming email payload.
     *
     * @param array $emailData
     * @return \App\Models\Ticket|null
     */
    public function processEmail(array $emailData): ?Ticket
    {
        $user = User::where('email', $emailData['from_email'])->first();
        $userId = $user ? $user->id : null;

        // Clean the body to keep only the new reply, removing quoted history
        $cleanBody = $this->stripEmailHistory($emailData['body']);

        $ticketId = $this->extractTicketIdFromSubject($emailData['subject']);

        if ($ticketId) {
            return $this->appendMessageToTicket($ticketId, $userId, $cleanBody, $emailData['from_email']);
        }

        return $this->createNewTicket($userId, $emailData['subject'], $cleanBody, $emailData['from_email']);
    }

    /**
     * Removes previous message history (quoted text) from the email body.
     *
     * @param string $body
     * @return string
     */
    private function stripEmailHistory(string $body): string
    {
        $patterns = [
            '/^---/m',
            '/^________________________________/m',
            '/^From:/mi',
            '/^De:/mi',
            '/^On.*wrote:/mi',
            '/^Em.*escreveu:/mi'
        ];

        foreach ($patterns as $pattern) {
            $parts = preg_split($pattern, $body);
            if (count($parts) > 1) {
                $body = $parts[0];
                break;
            }
        }

        return trim($body);
    }

    private function extractTicketIdFromSubject(string $subject): ?int
    {
        if (preg_match('/\[(\d+)\]/', $subject, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

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

        Mail::to($senderEmail)->send(new TicketCreatedAutoReply($ticket));

        return $ticket;
    }

    private function appendMessageToTicket(int $ticketId, ?int $userId, string $body, string $senderEmail): ?Ticket
    {
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            Log::warning("Email reply failed: Ticket #{$ticketId} not found.");
            return null;
        }

        TicketMessage::create([
            'ticket_id'    => $ticket->id,
            'user_id'      => $userId,
            'sender_email' => $userId ? null : $senderEmail,
            'message'      => $body,
        ]);

        $ticket->touch();
        return $ticket;
    }
}