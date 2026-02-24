<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test if a user can login via API and receive a token
     */
    public function test_user_can_login_via_api(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
            'device_name' => 'MobileApp',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'role']
            ]);
    }

    /**
     * Test login failure with wrong credentials
     */
    public function test_api_login_fails_with_wrong_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'MobileApp',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test if user can logout and revoke token
     */
    public function test_user_can_logout_via_api(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('TestToken')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $response->assertStatus(200);
        $this->assertCount(0, $user->tokens);
    }
}