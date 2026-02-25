<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Notifications\TicketNotification;

/**
 * Observador responsável por despachar as lógicas reativas associadas
 * ao modelo Ticket, como notificações de estado.
 */
class TicketObserver
{
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
            $message = "O ticket #{$ticket->id} mudou para {$ticket->status->value}";

            // Notifica o Cliente caso ele não seja o responsável pela alteração
            if ($ticket->customer_id !== $actorId) {
                if ($ticket->customer) {
                    // Adicionado explicitamente o tipo 'status_change'
                    $ticket->customer->notify(new TicketNotification($ticket, $message, 'status_change'));
                }
            }

            // Notifica o Agente Atribuído caso ele não seja o responsável pela alteração
            if ($ticket->assigned_to && $ticket->assigned_to !== $actorId) {
                if ($ticket->assignee) {
                    // Adicionado explicitamente o tipo 'status_change'
                    $ticket->assignee->notify(new TicketNotification($ticket, $message, 'status_change'));
                }
            }
        }
    }
}