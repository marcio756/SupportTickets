<?php

namespace Tests\Feature\Api;

use App\Models\Ticket;
use App\Models\User;
use App\Enums\RoleEnum;
use App\Enums\TicketStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_list_only_their_tickets(): void
    {
        $customer = User::factory()->create(['role' => RoleEnum::CUSTOMER]);
        $otherCustomer = User::factory()->create(['role' => RoleEnum::CUSTOMER]);
        
        Ticket::factory()->count(3)->create(['customer_id' => $customer->id]);
        Ticket::factory()->count(2)->create(['customer_id' => $otherCustomer->id]);

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson('/api/tickets');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_send_message_to_ticket(): void
    {
        $customer = User::factory()->create([
            'role' => RoleEnum::CUSTOMER,
            'daily_support_seconds' => 1800 
        ]);
        
        // Correção: Garantir que o ticket está EM PROGRESSO para respeitar as novas regras de negócio do TicketService
        $ticket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'status' => TicketStatusEnum::IN_PROGRESS
        ]);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/tickets/{$ticket->id}/messages", [
                'message' => 'Hello from mobile app!',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'message' => 'Hello from mobile app!',
        ]);
    }
}