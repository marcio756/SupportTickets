<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that only supporters can view the activity logs page.
     */
    public function test_only_supporters_can_access_activity_logs(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $supporter = User::factory()->create(['role' => 'supporter']);

        // Assert customer is blocked
        $responseCustomer = $this->actingAs($customer)->get(route('activity-logs.index'));
        $responseCustomer->assertStatus(403);

        // Assert supporter is allowed
        $responseSupporter = $this->actingAs($supporter)->get(route('activity-logs.index'));
        $responseSupporter->assertStatus(200);
        $responseSupporter->assertInertia(fn ($page) => $page->component('ActivityLog/Index'));
    }
}