<?php

namespace Tests\Feature\Api;

use App\Models\Team;
use App\Models\User;
use App\Models\Vacation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VacationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_update_vacation_status(): void
    {
        /**
         * Verifies the state transition mechanism for vacations. 
         * Only valid states should be accepted to maintain data integrity in the calendar.
         */
        $admin = User::factory()->create(['role' => 'admin']);
        $vacation = Vacation::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($admin)->patchJson("/api/vacations/{$vacation->id}/status", [
            'status' => 'approved',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('vacations', [
            'id' => $vacation->id,
            'status' => 'approved',
        ]);
    }

    public function test_calendar_endpoint_returns_structured_data(): void
    {
        /**
         * Ensures the calendar endpoint aggregates teams, users, and vacations 
         * in the exact hierarchical format required by the frontend client.
         */
        $admin = User::factory()->create(['role' => 'admin']);
        $team = Team::factory()->create(['name' => 'Alpha', 'shift' => 'morning']);
        $user = User::factory()->create(['team_id' => $team->id]);
        
        Vacation::factory()->create([
            'supporter_id' => $user->id,
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
            'total_days' => 4,
            'year' => Carbon::now()->year,
            'status' => 'approved'
        ]);

        $response = $this->actingAs($admin)->getJson('/api/vacations/calendar');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         'teams' => [
                             '*' => [
                                 'id', 'name', 'shift', 'members' => [
                                     '*' => [
                                         'id', 'name', 'vacation_summary' => [
                                             'total_allowed', 'used_days', 'remaining_days'
                                         ],
                                         'vacations' => [
                                             '*' => [
                                                 'id', 'start_date', 'end_date', 'total_days', 'status'
                                             ]
                                         ]
                                     ]
                                 ]
                             ]
                         ]
                     ]
                 ]);
    }
}