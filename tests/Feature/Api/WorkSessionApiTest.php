<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Enums\RoleEnum;
use App\Enums\WorkSessionStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkSessionApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test starting a work session for a supporter.
     */
    public function test_supporter_can_start_work_session(): void
    {
        $user = User::factory()->create(['role' => RoleEnum::SUPPORTER]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson(route('api.work-sessions.start'));

        $response->assertStatus(201)
            ->assertJsonPath('data.status', WorkSessionStatusEnum::ACTIVE->value);

        $this->assertDatabaseHas('work_sessions', [
            'user_id' => $user->id,
            'status' => WorkSessionStatusEnum::ACTIVE->value,
            'ended_at' => null
        ]);
    }

    /**
     * Test that customers cannot access work sessions.
     */
    public function test_customer_cannot_access_work_sessions(): void
    {
        $user = User::factory()->create(['role' => RoleEnum::CUSTOMER]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson(route('api.work-sessions.start'));

        $response->assertStatus(403);
    }
}