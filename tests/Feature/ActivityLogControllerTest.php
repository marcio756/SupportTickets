<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Integration tests for the Activity Log Controller.
 */
class ActivityLogControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure only administrators can access the activity logs.
     */
    public function test_supporters_cannot_access_activity_logs(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);

        $response = $this->actingAs($supporter)->get('/activity-logs');

        $response->assertStatus(403);
    }

    /**
     * Ensure administrators can view the logs successfully.
     */
    public function test_admins_can_view_activity_logs(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::ADMIN->value]);
        
        // Generate a dummy log entry
        activity()->log('System started');

        $response = $this->actingAs($admin)->get('/activity-logs');

        $response->assertStatus(200);
    }
}