<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Models\User;
use App\Models\Vacation;
use App\Services\VacationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VacationServiceTest extends TestCase
{
    use RefreshDatabase;

    private VacationService $vacationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vacationService = new VacationService();
    }

    public function test_can_book_valid_vacation(): void
    {
        $team = Team::factory()->create(['shift' => 'morning']);
        $supporter = User::factory()->create(['team_id' => $team->id]);

        $startDate = Carbon::create(2026, 8, 10); // Monday
        $endDate = Carbon::create(2026, 8, 14);   // Friday

        $vacation = $this->vacationService->bookVacation($supporter, $startDate, $endDate);

        $this->assertEquals(5, $vacation->total_days);
        $this->assertEquals(2026, $vacation->year);
        $this->assertDatabaseHas('vacations', ['id' => $vacation->id]);
    }

    public function test_prevents_booking_more_than_22_days_annually(): void
    {
        $team = Team::factory()->create();
        $supporter = User::factory()->create(['team_id' => $team->id]);

        // Book 20 days
        $this->vacationService->bookVacation(
            $supporter, 
            Carbon::create(2026, 7, 1), 
            Carbon::create(2026, 7, 28) // 20 working days
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The total vacation days per year cannot exceed 22.');

        // Try to book 3 more days (Total 23)
        $this->vacationService->bookVacation(
            $supporter, 
            Carbon::create(2026, 8, 10), 
            Carbon::create(2026, 8, 12)
        );
    }

    public function test_prevents_overlapping_vacations_in_same_team_and_shift(): void
    {
        $team = Team::factory()->create(['shift' => 'afternoon']);
        $supporter1 = User::factory()->create(['team_id' => $team->id]);
        $supporter2 = User::factory()->create(['team_id' => $team->id]);

        $this->vacationService->bookVacation(
            $supporter1, 
            Carbon::create(2026, 8, 10), 
            Carbon::create(2026, 8, 14)
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Overlapping vacation dates found within the same team and shift.');

        // Supporter 2 tries to book overlapping dates
        $this->vacationService->bookVacation(
            $supporter2, 
            Carbon::create(2026, 8, 12), 
            Carbon::create(2026, 8, 18)
        );
    }

    public function test_prevents_vacation_crossing_different_years(): void
    {
        $supporter = User::factory()->create();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Vacation dates must be within the same year.');

        $this->vacationService->bookVacation(
            $supporter, 
            Carbon::create(2026, 12, 28), 
            Carbon::create(2027, 1, 3)
        );
    }
}