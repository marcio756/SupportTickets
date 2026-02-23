<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;

class TicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $data;

    /**
     * Create a new notification instance.
     * * @param array $data Contains ticket_id, title, message, type
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Determine the delivery channels for the notification.
     * * @param object $notifiable
     * @return array
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Format the notification data for the database channel.
     * * @param object $notifiable
     * @return array
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->data['ticket_id'],
            'title'     => $this->data['title'],
            'message'   => $this->data['message'],
            'type'      => $this->data['type'],
        ];
    }

    /**
     * Format the notification data for real-time broadcasting via Laravel Echo.
     * * @param object $notifiable
     * @return BroadcastMessage
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id'         => $this->id,
            'data'       => $this->toArray($notifiable),
            'read_at'    => null,
            'created_at' => now()->toDateTimeString(),
        ]);
    }
}