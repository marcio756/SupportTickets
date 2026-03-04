<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\AnonymousNotifiable;
use Symfony\Component\Mime\Email;

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
        // Generate the exact same ID format used in the initial auto-reply
        $domain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
        $messageId = "<ticket-{$this->ticket->id}@{$domain}>";

        return (new MailMessage)
            ->subject("Re: " . $this->ticket->title)
            ->greeting(" ")    // Removes automatic "Hello"
            ->salutation(" ")  // Removes automatic "Regards"
            ->line($this->content) // <-- Corrigido aqui (estava $this->replyMessage)
            ->action('View Ticket', route('tickets.show', $this->ticket))
            ->line("________________________________")
            ->line("Reply above this line to continue the conversation in the ticket. Do not change the subject.")
            ->withSymfonyMessage(function (Email $message) use ($messageId) {
                // Injects RFC 2822 standard headers to force email clients to group this into the original thread
                $message->getHeaders()->addTextHeader('In-Reply-To', $messageId);
                $message->getHeaders()->addTextHeader('References', $messageId);
            });
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