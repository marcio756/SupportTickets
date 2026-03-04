<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerHierarchyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure a supporter can only view and manage customers.
     */
    public function test_supporter_can_only_manage_customers(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        $customer = User::factory()->create(['role' => RoleEnum::CUSTOMER->value]);
        $admin = User::factory()->create(['role' => RoleEnum::ADMIN->value]);

        $response = $this->actingAs($supporter)->get(route('users.index'));
        
        $response->assertStatus(200);
        $response->assertSee($customer->email);
        $response->assertDontSee($admin->email);
    }

    /**
     * Ensure the system prevents the deletion of the last administrator.
     */
    public function test_cannot_delete_last_admin(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::ADMIN->value]);
        
        // Simulating the required current_password confirmation via session
        $this->withSession(['auth.password_confirmed_at' => time()]);

        $response = $this->actingAs($admin)->delete(route('users.destroy', $admin), [
            'current_password' => 'password', // Default factory password
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    /**
     * Ensure an admin can view all users.
     */
    public function test_admin_can_manage_all_users(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::ADMIN->value]);
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);

        $response = $this->actingAs($admin)->get(route('users.index'));
        
        $response->assertStatus(200);
        $response->assertSee($supporter->email);
    }
}