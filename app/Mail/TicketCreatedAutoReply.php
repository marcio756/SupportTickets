<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Headers;

class TicketCreatedAutoReply extends Mailable
{
    use Queueable, SerializesModels;

    public Ticket $ticket;

    /**
     * Create a new message instance.
     */
    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'We received your ticket: ' . $this->ticket->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket_created_auto_reply',
            with: [
                'ticketId' => $this->ticket->id,
                'ticketTitle' => $this->ticket->title,
            ]
        );
    }

    /**
     * Add deterministic Message-ID to allow proper email threading.
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