<?php

namespace App\Observers;

use App\Models\TicketMessage;
use App\Notifications\TicketNotification;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Notification;

/**
 * Observer responsible for intercepting the creation of ticket messages
 * and dispatching internal and push notifications to the appropriate parties.
 */
class TicketMessageObserver
{
    /**
     * Create a new observer instance.
     *
     * @param FirebaseService $firebaseService Service injected for push notifications.
     */
    public function __construct(private FirebaseService $firebaseService) {}

    /**
     * Notify the alternate participant when a new ticket message is dispatched.
     *
     * @param TicketMessage $message The newly created ticket message.
     * @return void
     */
    public function created(TicketMessage $message): void
    {
        $ticket = $message->ticket;
        $senderId = $message->user_id;
        
        $title = "Nova Mensagem";
        $payload = ['ticket_id' => (string) $ticket->id];

        // Cenário 1: A mensagem foi enviada pelo cliente (com conta ou por e-mail)
        // O destinatário a ser notificado é o Supporter associado (se existir)
        if ($senderId === $ticket->customer_id || ($senderId === null && $ticket->sender_email)) {
            $recipient = $ticket->assignee;
            if ($recipient) {
                $recipient->notify(new TicketNotification($ticket, "O cliente enviou uma nova resposta.", 'new_message'));
                $this->firebaseService->sendNotificationToUser($recipient, $title, "Nova mensagem do cliente", $payload);
            }
            return;
        }

        // Cenário 2: A mensagem foi enviada pelo Supporter
        // O destinatário a ser notificado é o Cliente
        $body = "Recebeu uma nova resposta no ticket #{$ticket->id}.";

        if ($ticket->customer) {
            // Cliente possui conta registada no sistema
            $ticket->customer->notify(new TicketNotification($ticket, $body, 'new_message'));
            $this->firebaseService->sendNotificationToUser($ticket->customer, $title, $body, $payload);
            
        } elseif ($ticket->sender_email) {
            // Cliente NÃO possui conta (origem E-mail). Dispara Notificação Anónima
            Notification::route('mail', $ticket->sender_email)
                ->notify(new TicketNotification($ticket, $body, 'new_message'));
        }
    }
}