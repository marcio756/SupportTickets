<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Ticket;
use App\Models\WorkSession;
use App\Enums\RoleEnum;
use App\Enums\TicketStatusEnum;
use App\Enums\WorkSessionStatusEnum;
use App\Notifications\TicketNotification;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AutoAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_is_auto_assigned_to_available_supporter_with_capacity()
    {
        Notification::fake();

        // 1. Create a customer
        $customer = User::factory()->create(['role' => RoleEnum::CUSTOMER->value]);

        // 2. Create a Supporter WITH an active shift and 0 tickets
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        WorkSession::factory()->create([
            'user_id' => $supporter->id,
            'status' => WorkSessionStatusEnum::ACTIVE->value,
        ]);

        // 3. Create another Supporter WITH an active shift but full capacity (5 tickets)
        $busySupporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        WorkSession::factory()->create([
            'user_id' => $busySupporter->id,
            'status' => WorkSessionStatusEnum::ACTIVE->value,
        ]);
        Ticket::factory()->count(5)->create([
            'assigned_to' => $busySupporter->id,
            'status' => TicketStatusEnum::OPEN->value,
        ]);

        // 4. Create the new ticket using the Service
        $ticketService = app(TicketService::class);
        $ticket = $ticketService->createTicket([
            'title' => 'My internet is down',
            'description' => 'Please help me, no connection.',
            'priority' => 'high',
        ], $customer);

        // Assert: The ticket was assigned to the supporter with capacity, not the busy one
        $this->assertEquals($supporter->id, $ticket->assigned_to);
        $this->assertEquals(TicketStatusEnum::OPEN->value, $ticket->status);

        // Assert: The available supporter received a notification
        Notification::assertSentTo(
            $supporter,
            TicketNotification::class
        );
    }
}