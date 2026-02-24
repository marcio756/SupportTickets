<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\TicketNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Verifies that the Observer correctly identifies and dispatches events
 * upon the creation of new messages.
 */
class TicketMessageObserverTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verifies that adding a new message to a ticket correctly triggers
     * the system to send a notification to the relevant parties.
     * * @return void
     */
    public function test_it_dispatches_notification_when_a_message_is_created(): void
    {
        Notification::fake();

        $customer = User::factory()->create(['role' => 'customer']);
        $supporter = User::factory()->create(['role' => 'supporter']);

        $ticket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'title' => 'System Issue',
            'status' => 'in_progress',
            'assigned_to' => $supporter->id,
        ]);

        // Trigger the observer by creating a message
        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $customer->id,
            'message' => 'Need help with the login page.',
        ]);

        // Assert that the assigned supporter receives the correct Notification type
        Notification::assertSentTo(
            $supporter,
            TicketNotification::class
        );
    }
}