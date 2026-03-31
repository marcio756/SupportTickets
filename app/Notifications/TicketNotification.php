<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\AnonymousNotifiable;
use Symfony\Component\Mime\Email;

class TicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $maxExceptions = 3;

    public function __construct(
        public Ticket $ticket,
        public string $content,
        public string $type = 'new_message'
    ) {
        // Architect Note: Removida a obrigatoriedade da conexão 'redis' para evitar crash em ambientes sem a extensão PHP instalada.
        // O Laravel vai agora respeitar a variável QUEUE_CONNECTION do ficheiro .env
        $this->queue = 'notifications';
    }

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }

        $channels = ['database']; 

        if (!empty($notifiable->email) && !str_ends_with($notifiable->email, '@example.com')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $domain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
        $messageId = "<ticket-{$this->ticket->id}@{$domain}>";

        return (new MailMessage)
            ->subject("Re: " . $this->ticket->title)
            ->greeting(" ")    
            ->salutation(" ")  
            ->line($this->content) 
            ->action('View Ticket', route('tickets.show', $this->ticket))
            ->line("________________________________")
            ->line("Reply above this line to continue the conversation in the ticket. Do not change the subject.")
            ->withSymfonyMessage(function (Email $message) use ($messageId) {
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