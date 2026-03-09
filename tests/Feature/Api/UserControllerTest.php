<?php

namespace Tests\Feature\Api;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration tests for User Management API endpoints.
 */
class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure that a supporter can list users but cannot delete them.
     */
    public function test_supporter_can_list_but_cannot_delete_users(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        $customer = User::factory()->create(['role' => RoleEnum::CUSTOMER->value]);

        $this->actingAs($supporter);

        // Verify index access is allowed
        $response = $this->getJson('/api/users');
        $response->assertStatus(200);

        // Verify delete action is strictly forbidden for supporters
        $deleteResponse = $this->deleteJson("/api/users/{$customer->id}");
        $deleteResponse->assertStatus(403);
    }

    /**
     * Ensure that an admin can deactivate (soft delete) a user.
     */
    public function test_admin_can_deactivate_user(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::ADMIN->value]);
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);

        $this->actingAs($admin);

        $response = $this->deleteJson("/api/users/{$supporter->id}");
        
        $response->assertStatus(200)
                 ->assertJson(['message' => 'User deactivated successfully.']);
                 
        $this->assertSoftDeleted('users', ['id' => $supporter->id]);
    }

    /**
     * Ensure that the system prevents the deactivation of the last remaining admin.
     */
    public function test_admin_cannot_deactivate_last_admin(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::ADMIN->value]);

        $this->actingAs($admin);

        // Attempting to delete the only existing admin
        $response = $this->deleteJson("/api/users/{$admin->id}");
        
        // Custom rule enforcement
        $response->assertStatus(422)
                 ->assertJson(['message' => 'Cannot deactivate the last admin user.']);
                 
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'deleted_at' => null]);
    }

    /**
     * Ensure that an admin can restore a previously deactivated user.
     */
    public function test_admin_can_restore_deactivated_user(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::ADMIN->value]);
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        
        $supporter->delete(); // Soft delete the user
        $this->assertSoftDeleted('users', ['id' => $supporter->id]);

        $this->actingAs($admin);

        $response = $this->patchJson("/api/users/{$supporter->id}/restore");
        
        $response->assertStatus(200)
                 ->assertJson(['message' => 'User restored successfully.']);
                 
        $this->assertDatabaseHas('users', ['id' => $supporter->id, 'deleted_at' => null]);
    }
}