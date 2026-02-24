<?php

namespace Tests\Unit;

use App\Enums\RoleEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SupportTimeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Validates the core business logic related to customer support time deduction.
 */
class SupportTimeManagerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tests the time deduction logic for an in-progress ticket.
     * * @return void
     */
    public function test_it_deducts_time_from_customer_when_ticket_is_in_progress(): void
    {
        Event::fake();

        $customer = User::factory()->create([
            'role' => RoleEnum::CUSTOMER->value,
            'daily_support_seconds' => 1800,
        ]);

        $ticket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'status' => TicketStatusEnum::IN_PROGRESS->value, // Fix: Must be in progress
        ]);

        $manager = new SupportTimeManager();
        $remaining = $manager->deductTime($ticket, 5);

        $this->assertEquals(1795, $remaining);
        $this->assertEquals(1795, $customer->fresh()->daily_support_seconds);
        
        Event::assertDispatched(\App\Events\SupportTimeUpdated::class);
    }

    /**
     * Tests that the time deduction is safely ignored when a ticket is not actively in progress.
     * * @return void
     */
    public function test_it_does_not_deduct_time_if_ticket_is_not_in_progress(): void
    {
        $customer = User::factory()->create([
            'role' => RoleEnum::CUSTOMER->value,
            'daily_support_seconds' => 1800,
        ]);

        $ticket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'status' => TicketStatusEnum::OPEN->value, // Simulate an inactive status
        ]);

        $manager = new SupportTimeManager();
        $remaining = $manager->deductTime($ticket, 5);

        $this->assertEquals(1800, $remaining);
        $this->assertEquals(1800, $customer->fresh()->daily_support_seconds);
    }
}