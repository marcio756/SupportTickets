<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\WorkSessionStatusEnum;
use App\Models\User;
use App\Models\WorkSession;
use App\Models\WorkSessionPause;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkSessionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure a supporter can start a new work session successfully.
     */
    public function test_supporter_can_start_work_session(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);

        $response = $this->actingAs($supporter)->postJson(route('work-sessions.start'));

        $response->assertStatus(201);
        $this->assertDatabaseHas('work_sessions', [
            'user_id' => $supporter->id,
            'status' => WorkSessionStatusEnum::ACTIVE->value,
        ]);
    }

    /**
     * Prevent starting a new session if one is already active or paused.
     */
    public function test_cannot_start_session_if_already_open(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        
        WorkSession::create([
            'user_id' => $supporter->id,
            'status' => WorkSessionStatusEnum::ACTIVE->value,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($supporter)->postJson(route('work-sessions.start'));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    /**
     * Ensure an active session can be paused and creates a pause record.
     */
    public function test_supporter_can_pause_active_session(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        
        $session = WorkSession::create([
            'user_id' => $supporter->id,
            'status' => WorkSessionStatusEnum::ACTIVE->value,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($supporter)->postJson(route('work-sessions.pause'));

        $response->assertStatus(200);
        $this->assertDatabaseHas('work_sessions', [
            'id' => $session->id,
            'status' => WorkSessionStatusEnum::PAUSED->value,
        ]);
        $this->assertDatabaseHas('work_session_pauses', [
            'work_session_id' => $session->id,
            'ended_at' => null,
        ]);
    }

    /**
     * Ensure a paused session can be resumed, closing the open pause record.
     */
    public function test_supporter_can_resume_paused_session(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        
        $session = WorkSession::create([
            'user_id' => $supporter->id,
            'status' => WorkSessionStatusEnum::PAUSED->value,
            'started_at' => now()->subMinutes(30),
        ]);

        WorkSessionPause::create([
            'work_session_id' => $session->id,
            'started_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($supporter)->postJson(route('work-sessions.resume'));

        $response->assertStatus(200);
        $this->assertDatabaseHas('work_sessions', [
            'id' => $session->id,
            'status' => WorkSessionStatusEnum::ACTIVE->value,
        ]);
        
        // Assert the pause was closed
        $this->assertDatabaseMissing('work_session_pauses', [
            'work_session_id' => $session->id,
            'ended_at' => null,
        ]);
    }

    /**
     * Ensure a session is properly closed and total time is calculated (mocked loosely).
     */
    public function test_supporter_can_end_session(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        
        $session = WorkSession::create([
            'user_id' => $supporter->id,
            'status' => WorkSessionStatusEnum::ACTIVE->value,
            'started_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($supporter)->postJson(route('work-sessions.end'));

        $response->assertStatus(200);
        $this->assertDatabaseHas('work_sessions', [
            'id' => $session->id,
            'status' => WorkSessionStatusEnum::COMPLETED->value,
        ]);
        
        $session->refresh();
        $this->assertNotNull($session->ended_at);
        $this->assertNotNull($session->total_worked_seconds);
    }
}