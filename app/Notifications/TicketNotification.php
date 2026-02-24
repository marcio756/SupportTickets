<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Handles all system notifications related to tickets (status changes, new messages).
 * Dispatches to both database (UI bell) and email channels.
 */
class TicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Ticket $ticket;
    public string $message;

    /**
     * Create a new notification instance.
     * * @param Ticket $ticket
     * @param string $message
     */
    public function __construct(Ticket $ticket, string $message)
    {
        $this->ticket = $ticket;
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param object $notifiable
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Enforce dispatching to both UI and Email as per requirements
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param object $notifiable
     * @return MailMessage
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject("Atualização no Ticket #{$this->ticket->id}")
                    ->greeting("Olá, {$notifiable->name}!")
                    ->line($this->message)
                    ->action('Ver Ticket', route('tickets.show', $this->ticket->id))
                    ->line('Obrigado por usar a nossa plataforma de suporte técnico!');
    }

    /**
     * Get the array representation of the notification for the database (Sininho).
     *
     * @param object $notifiable
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'title' => $this->ticket->title,
            'message' => $this->message,
            'url' => route('tickets.show', $this->ticket->id),
        ];
    }
}