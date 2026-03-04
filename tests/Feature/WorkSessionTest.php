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

    public function test_supporter_can_start_work_session(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);

        $response = $this->actingAs($supporter)->post(route('work-sessions.start'));

        $response->assertRedirect();
        $this->assertDatabaseHas('work_sessions', [
            'user_id' => $supporter->id,
            'status' => WorkSessionStatusEnum::ACTIVE->value,
        ]);
    }

    public function test_cannot_start_session_if_already_open(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        
        WorkSession::create([
            'user_id' => $supporter->id,
            'status' => WorkSessionStatusEnum::ACTIVE->value,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($supporter)->post(route('work-sessions.start'), [], ['HTTP_REFERER' => '/']);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['status']);
    }

    public function test_supporter_can_pause_active_session(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        
        $session = WorkSession::create([
            'user_id' => $supporter->id,
            'status' => WorkSessionStatusEnum::ACTIVE->value,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($supporter)->post(route('work-sessions.pause'));

        $response->assertRedirect();
        $this->assertDatabaseHas('work_sessions', [
            'id' => $session->id,
            'status' => WorkSessionStatusEnum::PAUSED->value,
        ]);
    }

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

        $response = $this->actingAs($supporter)->post(route('work-sessions.resume'));

        $response->assertRedirect();
        $this->assertDatabaseHas('work_sessions', [
            'id' => $session->id,
            'status' => WorkSessionStatusEnum::ACTIVE->value,
        ]);
    }

    public function test_supporter_can_end_session(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        
        $session = WorkSession::create([
            'user_id' => $supporter->id,
            'status' => WorkSessionStatusEnum::ACTIVE->value,
            'started_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($supporter)->post(route('work-sessions.end'));

        $response->assertRedirect();
        $this->assertDatabaseHas('work_sessions', [
            'id' => $session->id,
            'status' => WorkSessionStatusEnum::COMPLETED->value,
        ]);
    }

    /**
     * @group deletion
     */
    public function test_admin_can_delete_work_session_and_it_is_logged(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::ADMIN->value]);
        $session = WorkSession::factory()->create();

        $response = $this->actingAs($admin)->delete(route('work-sessions.destroy', $session));

        $response->assertRedirect();
        $this->assertDatabaseMissing('work_sessions', ['id' => $session->id]);
        
        // Verify activity log entry exists for this specific deletion
        $this->assertDatabaseHas('activity_log', [
            'event' => 'deleted',
            'subject_type' => WorkSession::class,
            'causer_id' => $admin->id
        ]);
    }

    /**
     * @group deletion
     */
    public function test_supporter_cannot_delete_work_sessions(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        $session = WorkSession::factory()->create();

        $response = $this->actingAs($supporter)->delete(route('work-sessions.destroy', $session));

        $response->assertStatus(403);
        $this->assertDatabaseHas('work_sessions', ['id' => $session->id]);
    }
}