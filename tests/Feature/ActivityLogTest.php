<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that only admins can view the activity logs page.
     * Customers and Supporters should be blocked.
     */
    public function test_only_admins_can_access_activity_logs(): void
    {
        $customer = User::factory()->create(['role' => RoleEnum::CUSTOMER->value]);
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        $admin = User::factory()->create(['role' => RoleEnum::ADMIN->value]);

        $responseCustomer = $this->actingAs($customer)->get(route('activity-logs.index'));
        $responseCustomer->assertStatus(403);

        $responseSupporter = $this->actingAs($supporter)->get(route('activity-logs.index'));
        $responseSupporter->assertStatus(403);

        $responseAdmin = $this->actingAs($admin)->get(route('activity-logs.index'));
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertInertia(fn ($page) => $page->component('ActivityLog/Index'));
    }
}