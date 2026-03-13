<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vacation;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class VacationService
{
    private const MAX_ANNUAL_VACATION_DAYS = 22;

    public function bookVacation(User $supporter, Carbon $startDate, Carbon $endDate): Vacation
    {
        if ($startDate->year !== $endDate->year) {
            throw ValidationException::withMessages(['dates' => 'Vacation dates must be within the same year.']);
        }

        $year = $startDate->year;
        
        /**
         * Calculates actual business days to prevent consuming quota on weekends.
         * Public holidays should ideally be injected here via a separate HolidaysManager in the future.
         */
        $workingDays = 0;
        foreach ($startDate->daysUntil($endDate) as $date) {
            if ($date->isWeekday()) {
                $workingDays++;
            }
        }

        if ($workingDays === 0) {
            throw ValidationException::withMessages(['dates' => 'The selected period contains no working days.']);
        }

        $this->ensureAnnualLimitIsNotExceeded($supporter, $year, $workingDays);
        $this->ensureNoOverlappingSelfVacations($supporter, $startDate, $endDate);
        $this->ensureNoOverlappingTeamVacations($supporter, $startDate, $endDate);

        return Vacation::create([
            'supporter_id' => $supporter->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_days' => $workingDays,
            'year' => $year,
            'status' => 'pending',
        ]);
    }

    public function updateStatus(Vacation $vacation, string $status): Vacation
    {
        $vacation->update(['status' => $status]);
        return $vacation;
    }

    private function ensureAnnualLimitIsNotExceeded(User $supporter, int $year, int $requestedDays): void
    {
        $usedDays = Vacation::where('supporter_id', $supporter->id)
            ->where('year', $year)
            ->where('status', '!=', 'rejected')
            ->sum('total_days');

        if (($usedDays + $requestedDays) > self::MAX_ANNUAL_VACATION_DAYS) {
            throw ValidationException::withMessages(['total_days' => 'The total vacation days per year cannot exceed 22.']);
        }
    }

    private function ensureNoOverlappingSelfVacations(User $supporter, Carbon $startDate, Carbon $endDate): void
    {
        $overlapExists = Vacation::where('supporter_id', $supporter->id)
            ->where('status', '!=', 'rejected')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function ($q) use ($startDate, $endDate) {
                          $q->where('start_date', '<=', $startDate)->where('end_date', '>=', $endDate);
                      });
            })->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages(['dates' => 'You already have a vacation booked during these dates.']);
        }
    }

    private function ensureNoOverlappingTeamVacations(User $supporter, Carbon $startDate, Carbon $endDate): void
    {
        if (!$supporter->team_id) return;

        /**
         * Teams inherently represent a specific shift in this architecture. 
         * By checking overlaps within the same team, we automatically enforce 
         * the "no overlap between members of the same shift" requirement.
         */
        $teamMemberIds = User::where('team_id', $supporter->team_id)->where('id', '!=', $supporter->id)->pluck('id');
        if ($teamMemberIds->isEmpty()) return;

        $overlapExists = Vacation::whereIn('supporter_id', $teamMemberIds)
            ->where('status', '!=', 'rejected')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function ($q) use ($startDate, $endDate) {
                          $q->where('start_date', '<=', $startDate)->where('end_date', '>=', $endDate);
                      });
            })->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages(['dates' => 'Overlapping vacation dates found within the same team and shift.']);
        }
    }
}