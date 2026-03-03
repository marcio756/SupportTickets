<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class TicketCreatedAutoReply extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Ticket $ticket) {}

    public function envelope(): Envelope
    {
        // Aqui formatamos o Assunto (Subject) exigido pelo professor
        return new Envelope(
            subject: '[' . $this->ticket->id . '] Novo ticket criado',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket_created_auto_reply',
        );
    }

    /**
     * Defines custom headers for the message, generating a deterministic Message-ID.
     * This acts as the anchor for all future replies to thread properly in email clients.
     *
     * @return Headers
     */
    public function headers(): Headers
    {
        $domain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';

        return new Headers(
            messageId: "ticket-{$this->ticket->id}@{$domain}",
        );
    }
}