<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Channels\DiscordWebhookChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notification mapped to dispatch newly created tickets to Discord.
 *
 * Implements ShouldQueue to guarantee the HTTP request runs asynchronously,
 * ensuring the end-user interface remains fast and fluid (Perceived Performance).
 */
class TicketCreatedDiscordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param Ticket $ticket The newly created ticket instance.
     */
    public function __construct(
        public Ticket $ticket
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return [DiscordWebhookChannel::class];
    }

    /**
     * Format the rich embed payload for the Discord webhook.
     *
     * @param  mixed  $notifiable
     * @return array<string, mixed>
     */
    public function toDiscord(mixed $notifiable): array
    {
        // Extração das propriedades para variáveis com Null Coalescing e Nullsafe Operators
        // Isto resolve o Syntax Error de interpolação e protege contra relações não carregadas
        $title = $this->ticket->title ?? 'Sem Título';
        $status = $this->ticket->status?->value ?? 'Pendente';
        $userName = $this->ticket->user?->name ?? 'Desconhecido';

        return [
            'content' => "🚨 **Novo Ticket de Suporte Submetido!**",
            'embeds' => [
                [
                    'title' => "Ticket #{$this->ticket->id}: {$title}",
                    'description' => "Um novo ticket foi criado no sistema e aguarda atribuição.",
                    'color' => 3447003, // Premium Blue Hex equivalent
                    'fields' => [
                        [
                            'name' => 'Estado',
                            'value' => $status,
                            'inline' => true
                        ],
                        [
                            'name' => 'Cliente / Autor',
                            'value' => $userName,
                            'inline' => true
                        ]
                    ],
                    'timestamp' => now()->toIso8601String(),
                ]
            ]
        ];
    }
}