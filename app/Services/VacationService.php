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
            throw ValidationException::withMessages(['start_date' => 'Vacation dates must be within the same year.']);
        }

        $year = $startDate->year;
        $workingDays = $this->calculateWorkingDays($startDate, $endDate);

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

    public function updateVacation(Vacation $vacation, Carbon $startDate, Carbon $endDate, string $status): Vacation
    {
        if ($startDate->year !== $endDate->year) {
            throw ValidationException::withMessages(['start_date' => 'Dates must be within the same year.']);
        }

        $workingDays = $this->calculateWorkingDays($startDate, $endDate);
        
        $vacation->loadMissing('supporter');
        $supporter = $vacation->supporter;

        // Só aplicamos regras de limite e sobreposição de equipa se a intenção não for rejeitar as férias
        if ($status !== 'rejected') {
            $this->ensureAnnualLimitIsNotExceeded($supporter, $startDate->year, $workingDays, $vacation->id);
            $this->ensureNoOverlappingSelfVacations($supporter, $startDate, $endDate, $vacation->id);
            $this->ensureNoOverlappingTeamVacations($supporter, $startDate, $endDate, $vacation->id);
        }

        $vacation->update([
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_days' => $workingDays,
            'status' => $status,
        ]);

        return $vacation;
    }

    public function updateStatus(Vacation $vacation, string $status): Vacation
    {
        // Rencaminha para o update completo para garantir que a simples aprovação não gera conflito tardio
        return $this->updateVacation(
            $vacation, 
            Carbon::parse($vacation->start_date), 
            Carbon::parse($vacation->end_date), 
            $status
        );
    }

    private function calculateWorkingDays(Carbon $startDate, Carbon $endDate): int
    {
        $workingDays = 0;
        foreach ($startDate->daysUntil($endDate) as $date) {
            if ($date->isWeekday()) {
                $workingDays++;
            }
        }

        if ($workingDays === 0) {
            throw ValidationException::withMessages(['start_date' => 'The selected period contains no working days.']);
        }

        return $workingDays;
    }

    private function ensureAnnualLimitIsNotExceeded(User $supporter, int $year, int $requestedDays, ?int $ignoreVacationId = null): void
    {
        $query = Vacation::where('supporter_id', $supporter->id)
            ->where('year', $year)
            ->where('status', '!=', 'rejected');

        if ($ignoreVacationId) {
            $query->where('id', '!=', $ignoreVacationId);
        }

        $usedDays = $query->sum('total_days');

        if (($usedDays + $requestedDays) > self::MAX_ANNUAL_VACATION_DAYS) {
            throw ValidationException::withMessages(['start_date' => 'The total vacation days per year cannot exceed 22.']);
        }
    }

    private function ensureNoOverlappingSelfVacations(User $supporter, Carbon $startDate, Carbon $endDate, ?int $ignoreVacationId = null): void
    {
        $query = Vacation::where('supporter_id', $supporter->id)
            ->where('status', '!=', 'rejected')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function ($q) use ($startDate, $endDate) {
                          $q->where('start_date', '<=', $startDate)->where('end_date', '>=', $endDate);
                      });
            });

        if ($ignoreVacationId) {
            $query->where('id', '!=', $ignoreVacationId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['start_date' => 'You already have a vacation booked during these dates.']);
        }
    }

    private function ensureNoOverlappingTeamVacations(User $supporter, Carbon $startDate, Carbon $endDate, ?int $ignoreVacationId = null): void
    {
        if (!$supporter->team_id) return;

        $teamMemberIds = User::where('team_id', $supporter->team_id)->where('id', '!=', $supporter->id)->pluck('id');
        if ($teamMemberIds->isEmpty()) return;

        $query = Vacation::whereIn('supporter_id', $teamMemberIds)
            ->where('status', '!=', 'rejected')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function ($q) use ($startDate, $endDate) {
                          $q->where('start_date', '<=', $startDate)->where('end_date', '>=', $endDate);
                      });
            });

        if ($ignoreVacationId) {
            $query->where('id', '!=', $ignoreVacationId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['start_date' => 'Overlapping vacation dates found within the same team and shift.']);
        }
    }
}