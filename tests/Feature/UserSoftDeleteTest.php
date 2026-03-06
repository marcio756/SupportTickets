<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_soft_delete_customer(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::ADMIN->value]);
        $customer = User::factory()->create(['role' => RoleEnum::CUSTOMER->value]);

        $response = $this->actingAs($admin)->delete(route('users.destroy', $customer), [
            'current_password' => 'password',
        ]);

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertSoftDeleted($customer);
    }

    public function test_admin_can_restore_soft_deleted_user(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::ADMIN->value]);
        
        $customer = User::factory()->create(['role' => RoleEnum::CUSTOMER->value]);
        $customer->delete(); // Soft delete the user

        $this->assertSoftDeleted($customer);

        $response = $this->actingAs($admin)->patch(route('users.restore', $customer->id));

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertNotSoftDeleted($customer);
    }

    public function test_supporter_cannot_restore_users(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        
        $customer = User::factory()->create(['role' => RoleEnum::CUSTOMER->value]);
        $customer->delete();

        $response = $this->actingAs($supporter)->patch(route('users.restore', $customer->id));

        $response->assertForbidden();
        $this->assertSoftDeleted($customer);
    }
}