<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a user can update their password via API.
     */
    public function test_user_can_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old_password')
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson(route('api.me.password'), [
                'current_password' => 'old_password',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Password alterada com sucesso.');

        $this->assertTrue(Hash::check('NewPassword123!', $user->refresh()->password));
    }

    /**
     * Test validation failure on password update.
     */
    public function test_password_update_requires_correct_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct_password')
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson(route('api.me.password'), [
                'current_password' => 'wrong_password',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }
}