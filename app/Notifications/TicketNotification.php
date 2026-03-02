<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\AnonymousNotifiable;
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
     * @param string $type Ajuda o frontend a agrupar notificações semelhantes.
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
        // Se for um utilizador não registado (e-mail avulso), apenas enviamos e-mail.
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }

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
        // Garante que não dá erro ao procurar o nome num utilizador anónimo
        $name = $notifiable instanceof AnonymousNotifiable ? 'Cliente' : ($notifiable->name ?? 'Cliente');
        
        $email = $notifiable instanceof AnonymousNotifiable 
            ? $notifiable->routes['mail'] 
            : ($notifiable->email ?? 'Desconhecido');

        Log::channel('email')->info("📩 E-mail simulado para {$email}: {$this->message}");

        return (new MailMessage)
                    // OBRIGATÓRIO: Passar o ID entre parênteses retos para o CronJob do IMAP conseguir associar a resposta!
                    ->subject("[{$this->ticket->id}] Nova resposta no seu ticket")
                    ->greeting("Olá, {$name}!")
                    ->line($this->message)
                    ->line("⚠️ IMPORTANTE: Pode responder diretamente a este e-mail para continuar a conversa. Não altere o Assunto!")
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