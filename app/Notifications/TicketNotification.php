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
 * Architect Note: Implements ShouldQueue to ensure notification dispatching 
 * does not block database transactions or HTTP requests under high load.
 */
class TicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Define the number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Define the maximum number of exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    public function __construct(
        public Ticket $ticket,
        public string $content,
        public string $type = 'new_message'
    ) {
        // Architect Note: Explicitly setting the connection to 'redis' (or whatever queue driver is configured)
        // Ensure you have a queue worker running (e.g., `php artisan queue:work redis`)
        $this->connection = 'redis'; 
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