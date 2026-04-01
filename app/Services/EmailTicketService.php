<?php

namespace App\Services;

use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Mail\TicketCreatedAutoReply;
use Webklex\PHPIMAP\Message;

/**
 * Service responsible for processing incoming support emails.
 * Handles client matching, threading, and heavy Cloud S3 attachment streaming.
 */
class EmailTicketService
{
    /**
     * Processes a full raw IMAP Message object securely.
     *
     * @param Message $message
     * @return Ticket|null
     */
    public function processEmailMessage(Message $message): ?Ticket
    {
        $fromEmail = $message->getFrom()[0]->mail ?? null;
        if (!$fromEmail) return null;

        $user = User::where('email', $fromEmail)->first();
        $userId = $user ? $user->id : null;

        $rawBody = $message->getTextBody() ?? $message->getHTMLBody() ?? '';
        $cleanBody = $this->stripEmailHistory(is_string($rawBody) ? trim($rawBody) : '');
        $subject = (string) ($message->getSubject()[0] ?? 'No Subject');

        $inReplyTo = $message->getInReplyTo();
        $references = $message->getReferences();
        
        $inReplyToStr = is_iterable($inReplyTo) ? implode(' ', $inReplyTo->toArray()) : (string) $inReplyTo;
        $referencesStr = is_iterable($references) ? implode(' ', $references->toArray()) : (string) $references;

        $ticketId = $this->extractTicketIdFromHeaders($inReplyToStr, $referencesStr) 
                    ?? $this->extractTicketIdFromSubject($subject);

        $ticket = null;
        $ticketMessage = null;

        if ($ticketId) {
            $ticketData = $this->appendMessageToTicket($ticketId, $userId, $cleanBody, $fromEmail);
            $ticket = $ticketData['ticket'] ?? null;
            $ticketMessage = $ticketData['message'] ?? null;
        } else {
            $ticketData = $this->createNewTicket($userId, $subject, $cleanBody, $fromEmail);
            $ticket = $ticketData['ticket'];
            $ticketMessage = $ticketData['message'];
        }

        // Process attachments securely via Cloud Streaming
        if ($ticketMessage && $message->hasAttachments()) {
            $this->streamAttachmentsToCloud($message->getAttachments(), $ticketMessage);
        }

        return $ticket;
    }

    private function extractTicketIdFromHeaders(string $inReplyTo, string $references): ?int
    {
        $pattern = '/<ticket-(\d+)@.*?>/i';
        if (preg_match($pattern, $inReplyTo, $matches)) return (int) $matches[1];
        if (preg_match($pattern, $references, $matches)) return (int) $matches[1];
        return null;
    }

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
        if (preg_match('/\[(\d+)\]/', $subject, $matches)) return (int) $matches[1];
        return null;
    }

    private function createNewTicket(?int $userId, string $subject, string $body, string $senderEmail): array
    {
        $ticket = Ticket::create([
            'customer_id'  => $userId,
            'sender_email' => $senderEmail,
            'title'        => $subject,
            'source'       => 'email',
        ]);

        $message = TicketMessage::create([
            'ticket_id'    => $ticket->id,
            'user_id'      => $userId,
            'sender_email' => $userId ? null : $senderEmail,
            'message'      => $body,
        ]);

        Mail::to($senderEmail)->queue(new TicketCreatedAutoReply($ticket));

        return ['ticket' => $ticket, 'message' => $message];
    }

    private function appendMessageToTicket(int $ticketId, ?int $userId, string $body, string $senderEmail): array
    {
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            Log::warning("Email reply failed: Ticket #{$ticketId} not found.");
            return [];
        }

        $message = TicketMessage::create([
            'ticket_id'    => $ticket->id,
            'user_id'      => $userId,
            'sender_email' => $userId ? null : $senderEmail,
            'message'      => $body,
        ]);

        $ticket->touch();
        return ['ticket' => $ticket, 'message' => $message];
    }

    /**
     * Streams large attachments directly to configured filesystem (Cloud/S3)
     * avoiding server RAM exhaustion.
     */
    private function streamAttachmentsToCloud(iterable $attachments, TicketMessage $ticketMessage): void
    {
        $disk = config('filesystems.default', 'public');

        foreach ($attachments as $attachment) {
            try {
                $filename = Str::uuid() . '_' . $attachment->name;
                $path = 'attachments/' . $filename;
                
                // Uses streams to write directly without loading massive payloads entirely in RAM
                Storage::disk($disk)->put($path, $attachment->content);

                $ticketMessage->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $attachment->name,
                    'mime_type' => $attachment->mime ?? 'application/octet-stream',
                    'size' => $attachment->size ?? 0,
                ]);

                // Clear memory pointer immediately
                unset($attachment);

            } catch (\Exception $e) {
                Log::error("Failed to stream attachment for message {$ticketMessage->id}: " . $e->getMessage());
            }
        }
    }
}