<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Ensures strict security measures are in place for real-time WebSocket communication.
 */
class TicketChannelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bootstrap the test environment.
     * Enforces the Reverb driver to ensure broadcast authorization rules are strictly evaluated.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Overrides the default 'log' or 'null' testing driver, which automatically bypasses authorization
        Config::set('broadcasting.default', 'reverb');
    }

    /**
     * Verifies that a customer cannot listen to another customer's ticket channel.
     *
     * @return void
     */
    public function test_customer_cannot_access_other_customers_channel(): void
    {
        // Correção: Passar a instância do Enum em vez da string (->value) para garantir o casting correto
        $owner = User::factory()->create(['role' => RoleEnum::CUSTOMER]);
        $intruder = User::factory()->create(['role' => RoleEnum::CUSTOMER]);
        
        $ticket = Ticket::factory()->create([
            'customer_id' => $owner->id,
            'title' => 'My Private Issue',
            'status' => TicketStatusEnum::OPEN,
        ]);

        // Refresh para garantir que a memória reflete os Casts do Eloquent
        $intruder->refresh();
        $this->actingAs($intruder);

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-ticket.{$ticket->id}",
            'socket_id' => '12345.67890'
        ]);

        $response->assertStatus(403);
    }

    /**
     * Verifies that a supporter can listen to any ticket channel.
     *
     * @return void
     */
    public function test_supporter_can_access_any_ticket_channel(): void
    {
        // Correção: Passar a instância do Enum em vez da string (->value) para garantir o casting correto
        $owner = User::factory()->create(['role' => RoleEnum::CUSTOMER]);
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER]);
        
        $ticket = Ticket::factory()->create([
            'customer_id' => $owner->id,
            'title' => 'My Private Issue',
            'status' => TicketStatusEnum::OPEN,
        ]);

        // Refresh para garantir que o $user->role é um objeto Enum e passa na verificação estrita ===
        $supporter->refresh();
        $this->actingAs($supporter);

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-ticket.{$ticket->id}",
            'socket_id' => '12345.67890'
        ]);

        $response->assertStatus(200);
    }
}