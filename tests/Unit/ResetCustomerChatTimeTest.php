<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetCustomerChatTimeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verifies that the artisan command successfully resets the support time 
     * strictly for customers and ignores other roles.
     */
    public function test_it_resets_daily_support_time_for_customers_only(): void
    {
        $customer1 = User::factory()->create(['role' => 'customer', 'daily_support_seconds' => 10]);
        $customer2 = User::factory()->create(['role' => 'customer', 'daily_support_seconds' => 500]);
        $supporter = User::factory()->create(['role' => 'supporter', 'daily_support_seconds' => 0]);

        $this->artisan('support:reset-time')
            ->expectsOutput('Starting daily support time reset for customers...')
            ->assertExitCode(0);

        $this->assertEquals(1800, $customer1->fresh()->daily_support_seconds);
        $this->assertEquals(1800, $customer2->fresh()->daily_support_seconds);
        
        // Ensures the supporter account was not accidentally affected by the reset routine
        $this->assertEquals(0, $supporter->fresh()->daily_support_seconds);
    }
}