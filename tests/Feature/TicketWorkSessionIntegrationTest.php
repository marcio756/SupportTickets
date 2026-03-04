<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\TicketStatusEnum;
use App\Enums\WorkSessionStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketWorkSessionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure a supporter cannot modify a ticket if they do not have an active shift.
     */
    public function test_supporter_cannot_manage_ticket_without_active_session(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        $ticket = Ticket::factory()->create(['assigned_to' => $supporter->id]);

        $response = $this->actingAs($supporter)->patch(route('tickets.update-status', $ticket), [
            'status' => TicketStatusEnum::IN_PROGRESS->value,
        ]);

        $response->assertStatus(403);
        $response->assertSee('You must have an active work session');
    }

    /**
     * Ensure a supporter with an active shift can normally manage tickets.
     */
    public function test_supporter_can_manage_ticket_with_active_session(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        WorkSession::create([
            'user_id' => $supporter->id,
            'status' => WorkSessionStatusEnum::ACTIVE->value,
            'started_at' => now(),
        ]);

        $ticket = Ticket::factory()->create([
            'assigned_to' => $supporter->id,
            'status' => TicketStatusEnum::OPEN->value
        ]);

        $response = $this->actingAs($supporter)->patch(route('tickets.update-status', $ticket), [
            'status' => TicketStatusEnum::IN_PROGRESS->value,
        ]);

        $response->assertRedirect();
        $this->assertEquals(TicketStatusEnum::IN_PROGRESS, $ticket->fresh()->status);
    }

    /**
     * Ensure new tickets are auto-assigned only to online supporters with < 5 active tickets.
     */
    public function test_ticket_auto_assignment_to_available_supporter(): void
    {
        // Supporter 1: Online but busy (has 5 tickets)
        $busySupporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        WorkSession::create(['user_id' => $busySupporter->id, 'status' => WorkSessionStatusEnum::ACTIVE->value, 'started_at' => now()]);
        Ticket::factory()->count(5)->create(['assigned_to' => $busySupporter->id, 'status' => TicketStatusEnum::OPEN->value]);

        // Supporter 2: Online and available (has 2 tickets)
        $availableSupporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        WorkSession::create(['user_id' => $availableSupporter->id, 'status' => WorkSessionStatusEnum::ACTIVE->value, 'started_at' => now()]);
        Ticket::factory()->count(2)->create(['assigned_to' => $availableSupporter->id, 'status' => TicketStatusEnum::OPEN->value]);

        // Supporter 3: Offline
        User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        
        $customer = User::factory()->create(['role' => RoleEnum::CUSTOMER->value]);

        $this->actingAs($customer)->post(route('tickets.store'), [
            'title' => 'Auto Assignment Test',
            'message' => 'Help me please',
        ]);

        $ticket = Ticket::where('title', 'Auto Assignment Test')->first();

        // The ticket should be explicitly routed to Supporter 2
        $this->assertEquals($availableSupporter->id, $ticket->assigned_to);
    }
}