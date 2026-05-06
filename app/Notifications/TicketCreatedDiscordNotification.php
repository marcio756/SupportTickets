<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Channels\DiscordWebhookChannel;
use App\Models\Ticket;

/**
 * Represents a system notification triggered upon the creation of a new Support Ticket.
 * Implements ShouldQueue to guarantee that third-party network delays do not impact user perception of speed.
 */
class TicketCreatedDiscordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Ticket $ticket;

    /**
     * Initializes the notification state with the relevant ticket context.
     *
     * @param Ticket $ticket The newly created ticket instance.
     */
    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
    }

    /**
     * Designates the delivery channels for this specific notification.
     *
     * @param object $notifiable The target entity.
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [DiscordWebhookChannel::class];
    }

    /**
     * Assembles the notification data into a standard Discord Webhook payload structure (Rich Embed).
     *
     * @param object $notifiable The target entity.
     * @return array<string, mixed>
     */
    public function toDiscord(object $notifiable): array
    {
        // Vai buscar a primeira mensagem do ticket que gravámos no Passo 1
        $firstMessage = $this->ticket->messages()->oldest()->first();
        $description = $firstMessage ? $firstMessage->message : 'Sem descrição detalhada.';
        
        // Proteção contra quebra da API do Discord (limite do embed)
        if (mb_strlen($description) > 1000) {
            $description = mb_substr($description, 0, 1000) . '...';
        }

        return [
            'content' => null,
            'embeds' => [
                [
                    'title' => "📣 Novo Ticket Registado: #{$this->ticket->id} - {$this->ticket->title}",
                    'description' => $description,
                    'color' => 5814783, // Blue-ish premium accent
                    'fields' => [
                        [
                            'name' => 'Cliente',
                            // O ORM utiliza ->customer (BelongsTo User) em vez de ->user
                            'value' => $this->ticket->customer->name ?? $this->ticket->sender_email ?? 'Desconhecido',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Prioridade',
                            'value' => $this->ticket->priority ?? 'Normal',
                            'inline' => true,
                        ]
                    ],
                    'footer' => [
                        'text' => 'Sistema Automático de Suporte'
                    ],
                    'timestamp' => now()->toIso8601String(),
                ]
            ],
        ];
    }
}