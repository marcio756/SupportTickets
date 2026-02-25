<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Lida com todas as notificações do sistema relacionadas a tickets (alteração de estado, mensagens).
 * Despacha para a base de dados (Sininho UI) e canais de E-mail.
 */
class TicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Ticket $ticket;
    public string $message;
    public string $type;

    /**
     * Cria uma nova instância da notificação.
     *
     * @param Ticket $ticket
     * @param string $message
     * @param string $type Ajuda o frontend a agrupar notificações semelhantes (ex: 'new_message', 'status_change').
     */
    public function __construct(Ticket $ticket, string $message, string $type = 'new_message')
    {
        $this->ticket = $ticket;
        $this->message = $message;
        $this->type = $type;
    }

    /**
     * Obtém os canais de entrega da notificação.
     *
     * @param object $notifiable
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Obtém a representação de e-mail da notificação.
     *
     * @param object $notifiable
     * @return MailMessage
     */
    public function toMail(object $notifiable): MailMessage
    {
        Log::channel('email')->info("📩 E-mail simulado para {$notifiable->email}: {$this->message}");

        return (new MailMessage)
                    ->subject("Atualização no Ticket #{$this->ticket->id}")
                    ->greeting("Olá, {$notifiable->name}!")
                    ->line($this->message)
                    ->action('Ver Ticket', route('tickets.show', $this->ticket->id))
                    ->line('Obrigado por usar a nossa plataforma de suporte técnico!');
    }

    /**
     * Obtém a representação em array da notificação para a base de dados (Sininho).
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
            'type' => $this->type,
            'url' => route('tickets.show', $this->ticket->id),
        ];
    }
}