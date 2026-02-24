<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Ticket;
use App\Notifications\TicketNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Validates the notification lifecycle, including broadcasting and UI management.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful bulk deletion or marking of grouped notifications.
     * * @return void
     */
    public function test_can_mark_bulk_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Fix: We must construct a real ticket to match the updated Notification signature
        $ticket = Ticket::factory()->create([
            'customer_id' => $user->id,
        ]);

        // Generate mock notifications mapping to the exact Class requirement
        $user->notify(new TicketNotification($ticket, 'Message 1'));
        $user->notify(new TicketNotification($ticket, 'Message 2'));

        $notificationIds = $user->notifications()->pluck('id')->toArray();

        // Ensure notifications exist prior to the bulk action
        $this->assertCount(2, $user->notifications);

        $response = $this->postJson(route('notifications.read-bulk'), [
            'ids' => $notificationIds
        ]);

        $response->assertStatus(200);

        // Validate that notifications were effectively scrubbed from the DB
        $this->assertCount(0, $user->fresh()->unreadNotifications);
    }
}