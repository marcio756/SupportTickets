<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\WorkSessionStatusEnum;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkSessionReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_supporter_can_view_own_work_sessions(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        $otherSupporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);

        WorkSession::create([
            'user_id' => $supporter->id,
            'status' => WorkSessionStatusEnum::COMPLETED->value,
            'started_at' => now()->subHours(4),
            'ended_at' => now(),
            'total_worked_seconds' => 14400,
        ]);

        WorkSession::create([
            'user_id' => $otherSupporter->id,
            'status' => WorkSessionStatusEnum::COMPLETED->value,
            'started_at' => now()->subHours(4),
            'ended_at' => now(),
            'total_worked_seconds' => 14400,
        ]);

        $response = $this->actingAs($supporter)->get(route('work-sessions.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('WorkSessions/Index')
            ->has('sessions.data', 1) // Only sees his own 1 session
        );
    }

    public function test_admin_can_view_all_work_sessions(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::ADMIN->value]);
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);

        WorkSession::create([
            'user_id' => $supporter->id,
            'status' => WorkSessionStatusEnum::COMPLETED->value,
            'started_at' => now()->subHours(4),
            'ended_at' => now(),
            'total_worked_seconds' => 14400,
        ]);

        $response = $this->actingAs($admin)->get(route('work-sessions.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('WorkSessions/Index')
            ->has('sessions.data', 1)
            ->has('users') // Admin gets the filter list
        );
    }

    public function test_customer_cannot_access_reports(): void
    {
        $customer = User::factory()->create(['role' => RoleEnum::CUSTOMER->value]);

        $response = $this->actingAs($customer)->get(route('work-sessions.index'));

        $response->assertStatus(403);
    }
}