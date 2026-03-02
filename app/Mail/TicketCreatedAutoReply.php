<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
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
}