<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\WorkSessionStatusEnum;
use App\Models\Tag;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration tests for the Tag Management.
 */
class TagControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure supporters cannot create tags without an active session.
     */
    public function test_supporter_cannot_create_tag_without_active_session(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        
        $response = $this->actingAs($supporter)->post('/tags', [
            'name' => 'Urgent',
            'color' => '#ff0000',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('tags', ['name' => 'Urgent']);
    }

    /**
     * Ensure supporters can create tags if they have an active session.
     */
    public function test_supporter_can_create_tag_with_active_session(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        
        WorkSession::factory()->create([
            'user_id' => $supporter->id,
            'status' => WorkSessionStatusEnum::ACTIVE->value,
        ]);

        $response = $this->actingAs($supporter)->post('/tags', [
            'name' => 'Bug',
            'color' => '#000000',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tags', ['name' => 'Bug']);
    }

    /**
     * Ensure admins can bypass session checks and manage tags anytime.
     */
    public function test_admin_can_delete_tag_without_session(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::ADMIN->value]);
        $tag = Tag::factory()->create(['name' => 'Old Tag']);

        $response = $this->actingAs($admin)->delete("/tags/{$tag->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }
}