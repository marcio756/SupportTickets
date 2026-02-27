<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Notifications\TicketNotification;
use App\Services\FirebaseService;

/**
 * Observador responsável por despachar as lógicas reativas associadas
 * ao modelo Ticket, como notificações de estado e push via Firebase.
 */
class TicketObserver
{
    /**
     * Create a new observer instance.
     *
     * @param FirebaseService $firebaseService Service injected for push notifications.
     */
    public function __construct(private FirebaseService $firebaseService) {}

    /**
     * Notifica os utilizadores condicionalmente quando o estado do ticket muda.
     * Garante que o ator que fez a mudança não recebe a notificação.
     *
     * @param Ticket $ticket
     * @return void
     */
    public function updated(Ticket $ticket): void
    {
        // Verifica se a propriedade status foi efetivamente alterada
        if ($ticket->wasChanged('status')) {
            $actorId = auth()->id();
            
            // A mensagem a enviar na notificação
            $title = "Atualização de Ticket";
            $message = "O ticket #{$ticket->id} mudou para {$ticket->status->value}";
            $payload = ['ticket_id' => (string) $ticket->id];

            // Notifica o Cliente caso ele não seja o responsável pela alteração
            if ($ticket->customer_id !== $actorId) {
                if ($ticket->customer) {
                    $ticket->customer->notify(new TicketNotification($ticket, $message, 'status_change'));
                    $this->firebaseService->sendNotificationToUser($ticket->customer, $title, $message, $payload);
                }
            }

            // Notifica o Agente Atribuído caso ele não seja o responsável pela alteração
            if ($ticket->assigned_to && $ticket->assigned_to !== $actorId) {
                if ($ticket->assignee) {
                    $ticket->assignee->notify(new TicketNotification($ticket, $message, 'status_change'));
                    $this->firebaseService->sendNotificationToUser($ticket->assignee, $title, $message, $payload);
                }
            }
        }
    }
}