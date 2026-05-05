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
        return [
            'content' => null,
            'embeds' => [
                [
                    'title' => "🎫 Novo Ticket Registado: #{$this->ticket->id}",
                    'description' => $this->ticket->subject,
                    'color' => 5814783, // Blue-ish premium accent
                    'fields' => [
                        [
                            'name' => 'Cliente',
                            'value' => $this->ticket->user->name ?? 'Desconhecido',
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