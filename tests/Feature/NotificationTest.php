<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Notifications\TicketNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful bulk deletion of grouped notifications.
     * * @return void
     */
    public function test_can_mark_bulk_notifications_as_read()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Generate mock notifications mapping to the same virtual ticket logic
        $user->notify(new TicketNotification([
            'ticket_id' => 99,
            'title'     => 'Test 1',
            'message'   => 'Message 1',
            'type'      => 'new_message'
        ]));

        $user->notify(new TicketNotification([
            'ticket_id' => 99,
            'title'     => 'Test 2',
            'message'   => 'Message 2',
            'type'      => 'new_message'
        ]));

        $notificationIds = $user->notifications()->pluck('id')->toArray();

        // Ensure notifications exist prior to the bulk action
        $this->assertCount(2, $user->notifications);

        $response = $this->postJson(route('notifications.read-bulk'), [
            'ids' => $notificationIds
        ]);

        $response->assertStatus(200)
                 ->assertJson(['ticket_id' => 99]);

        // Validate that notifications were effectively scrubbed from the DB
        $this->assertCount(0, $user->fresh()->notifications);
    }
}