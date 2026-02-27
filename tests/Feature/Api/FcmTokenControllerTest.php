<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FcmTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test if an authenticated user can store an FCM token.
     *
     * @return void
     */
    public function test_authenticated_user_can_store_fcm_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/fcm-token', [
            'token' => 'test_fcm_token_123',
            'device_type' => 'web',
        ]);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'FCM Token registered successfully.']);

        $this->assertDatabaseHas('fcm_tokens', [
            'user_id' => $user->id,
            'token' => 'test_fcm_token_123',
            'device_type' => 'web',
        ]);
    }
}