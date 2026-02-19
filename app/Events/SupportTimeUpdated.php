<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event triggered when a customer's support time is decremented.
 * It broadcasts immediately to the frontend to sync the timer.
 */
class SupportTimeUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var int The ID of the ticket currently active.
     */
    public int $ticketId;

    /**
     * @var int The remaining support seconds for the customer.
     */
    public int $remainingSeconds;

    /**
     * Create a new event instance.
     *
     * @param int $ticketId
     * @param int $remainingSeconds
     */
    public function __construct(int $ticketId, int $remainingSeconds)
    {
        $this->ticketId = $ticketId;
        $this->remainingSeconds = $remainingSeconds;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('ticket.' . $this->ticketId),
        ];
    }
}