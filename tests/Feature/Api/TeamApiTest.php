<?php

namespace Tests\Feature\Api;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_assign_members_to_team(): void
    {
        /** * Setup context with an admin user and target entities to ensure 
         * the endpoint handles mass association properly without orphaned records.
         */
        $admin = User::factory()->create(['role' => 'admin']);
        $team = Team::factory()->create(['shift' => 'morning']);
        $users = User::factory()->count(3)->create(['team_id' => null]);

        $response = $this->actingAs($admin)->postJson("/api/teams/{$team->id}/members", [
            'user_ids' => $users->pluck('id')->toArray(),
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['message' => 'Members assigned successfully']);

        foreach ($users as $user) {
            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'team_id' => $team->id,
            ]);
        }
    }
}