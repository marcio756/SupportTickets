<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vacation;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Handles the business logic for booking and validating vacations.
 */
class VacationService
{
    private const MAX_ANNUAL_VACATION_DAYS = 22;

    /**
     * Validates and creates a new vacation record for a supporter.
     * Ensures dates do not overlap with team members and do not exceed annual limits.
     *
     * @param User $supporter
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Vacation
     * @throws ValidationException
     */
    public function bookVacation(User $supporter, Carbon $startDate, Carbon $endDate): Vacation
    {
        // 1. Validate year consistency
        if ($startDate->year !== $endDate->year) {
            throw ValidationException::withMessages([
                'dates' => 'Vacation dates must be within the same year.',
            ]);
        }

        $year = $startDate->year;

        // 2. Calculate working days (excluding weekends)
        $workingDays = $startDate->diffInDaysFiltered(function (Carbon $date) {
            return !$date->isWeekend();
        }, $endDate) + 1;

        // 3. Run Business Rule Validations
        $this->ensureAnnualLimitIsNotExceeded($supporter, $year, $workingDays);
        $this->ensureNoOverlappingSelfVacations($supporter, $startDate, $endDate);
        $this->ensureNoOverlappingTeamVacations($supporter, $startDate, $endDate);

        // 4. Persistence
        return Vacation::create([
            'supporter_id' => $supporter->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_days' => $workingDays,
            'year' => $year,
        ]);
    }

    /**
     * Checks if the requested days plus already used days exceed the annual limit.
     * * @param User $supporter
     * @param int $year
     * @param int $requestedDays
     * @throws ValidationException
     */
    private function ensureAnnualLimitIsNotExceeded(User $supporter, int $year, int $requestedDays): void
    {
        $usedDays = Vacation::where('supporter_id', $supporter->id)
            ->where('year', $year)
            ->sum('total_days');

        if (($usedDays + $requestedDays) > self::MAX_ANNUAL_VACATION_DAYS) {
            throw ValidationException::withMessages([
                'total_days' => 'The total vacation days per year cannot exceed 22.',
            ]);
        }
    }

    /**
     * Prevents the supporter from booking duplicate or overlapping vacations for themselves.
     * * @param User $supporter
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @throws ValidationException
     */
    private function ensureNoOverlappingSelfVacations(User $supporter, Carbon $startDate, Carbon $endDate): void
    {
        $overlapExists = Vacation::where('supporter_id', $supporter->id)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function ($q) use ($startDate, $endDate) {
                          $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                      });
            })
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'dates' => 'You already have a vacation booked during these dates.',
            ]);
        }
    }

    /**
     * Ensures that no other team member in the same shift has overlapping vacations.
     * * @param User $supporter
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @throws ValidationException
     */
    private function ensureNoOverlappingTeamVacations(User $supporter, Carbon $startDate, Carbon $endDate): void
    {
        if (!$supporter->team_id) {
            return;
        }

        // Fetch IDs of other members in the same team
        $teamMemberIds = User::where('team_id', $supporter->team_id)
            ->where('id', '!=', $supporter->id)
            ->pluck('id');

        if ($teamMemberIds->isEmpty()) {
            return;
        }

        // Check for any overlapping record among those team members
        $overlapExists = Vacation::whereIn('supporter_id', $teamMemberIds)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function ($q) use ($startDate, $endDate) {
                          $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                      });
            })
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'dates' => 'Overlapping vacation dates found within the same team and shift.',
            ]);
        }
    }
}