<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkSession;
use App\Enums\RoleEnum;
use App\Enums\WorkSessionStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkSessionReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that a supporter can only access their own work history.
     * This tests the core data isolation requirement.
     */
    public function test_supporter_can_only_see_own_sessions(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER]);
        $otherSupporter = User::factory()->create(['role' => RoleEnum::SUPPORTER]);

        // Create a session for the acting supporter
        WorkSession::factory()->create([
            'user_id' => $supporter->id,
            'status' => WorkSessionStatusEnum::COMPLETED,
            'started_at' => now()->subHours(2),
            'ended_at' => now(),
            'total_worked_seconds' => 7200
        ]);

        // Create a session for someone else
        WorkSession::factory()->create([
            'user_id' => $otherSupporter->id,
            'status' => WorkSessionStatusEnum::COMPLETED,
            'total_worked_seconds' => 3600
        ]);

        $response = $this->actingAs($supporter)->get(route('work-sessions.index'));

        $response->assertStatus(200);
        
        // Assert that only 1 record (the owner's) is returned
        $response->assertInertia(fn ($page) => $page
            ->has('sessions.data', 1)
            ->where('sessions.data.0.user.id', $supporter->id)
        );
    }

    /**
     * Verify that an admin can view and filter sessions from any supporter.
     */
    public function test_admin_can_filter_by_supporter(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::ADMIN]);
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER]);

        WorkSession::factory()->create([
            'user_id' => $supporter->id,
            'status' => WorkSessionStatusEnum::COMPLETED,
            'total_worked_seconds' => 3600
        ]);

        $response = $this->actingAs($admin)->get(route('work-sessions.index', [
            'user_id' => $supporter->id
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('sessions.data', 1)
            ->where('sessions.data.0.user.id', $supporter->id)
        );
    }
}