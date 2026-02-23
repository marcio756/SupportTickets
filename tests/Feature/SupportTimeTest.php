<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupportTimeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verifies that supporters can decrement time.
     */
    public function supporter_can_tick_customer_time_on_open_ticket(): void
    {
        $customer = User::factory()->create([
            'role' => RoleEnum::CUSTOMER,
            'daily_support_seconds' => 100,
        ]);

        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER]);

        $ticket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'status' => TicketStatusEnum::OPEN,
        ]);

        $response = $this->actingAs($supporter)->postJson(route('tickets.tick-time', $ticket));

        $response->assertStatus(200);
        $this->assertEquals(95, $customer->fresh()->daily_support_seconds);
    }

    public function test_unassigned_supporter_cannot_deduct_time_or_reply()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $assignedSupporter = User::factory()->create(['role' => 'supporter']);
        $noseySupporter = User::factory()->create(['role' => 'supporter']);

        $ticket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => $assignedSupporter->id,
            'status' => 'open'
        ]);

        // Attempt to tick time
        $response = $this->actingAs($noseySupporter)->postJson(route('tickets.tick-time', $ticket));
        $response->assertJson(['status' => 'not_assigned']);

        // Attempt to reply
        $replyResponse = $this->actingAs($noseySupporter)->post(route('tickets.messages.store', $ticket), [
            'message' => 'I am sneaking into this chat!'
        ]);
        $replyResponse->assertStatus(403);
    }

    /**
     * Verifies security layer for SRP and permissions.
     */
    public function customer_cannot_tick_their_own_time(): void
    {
        $customer = User::factory()->create(['role' => RoleEnum::CUSTOMER]);
        $ticket = Ticket::factory()->create(['customer_id' => $customer->id]);

        $response = $this->actingAs($customer)->postJson(route('tickets.tick-time', $ticket));

        $response->assertStatus(403);
    }

    /**
     * Verifies the daily reset command.
     */
    public function console_command_resets_time_properly(): void
    {
        $customer = User::factory()->create([
            'role' => RoleEnum::CUSTOMER,
            'daily_support_seconds' => 0,
        ]);

        $this->artisan('support:reset-time')
             ->expectsOutputToContain('Successfully reset time')
             ->assertSuccessful();

        $this->assertEquals(1800, $customer->fresh()->daily_support_seconds);
    }
}