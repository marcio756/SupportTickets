<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * Handles ticket updates and chat messages via email and database.
 */
class TicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public string $content,
        public string $type = 'new_message'
    ) {}

    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Re: [{$this->ticket->id}] Novo ticket criado")
            ->greeting(" ")    // Removes automatic "Hello"
            ->salutation(" ")  // Removes automatic "Regards"
            ->line($this->content)
            ->line("________________________________")
            ->line("Responda acima desta linha para continuar a conversa no ticket. Não altere o assunto.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'message'   => $this->content,
            'type'      => $this->type,
            'url'       => route('tickets.show', $this->ticket->id),
        ];
    }
}