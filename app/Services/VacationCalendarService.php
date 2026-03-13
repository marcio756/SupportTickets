<?php

namespace App\Services;

use App\Models\Team;
use Carbon\Carbon;

class VacationCalendarService
{
    /**
     * Aggregates a hierarchical representation of teams, users, and their vacations.
     * This decouples complex payload mapping from the controller and database layer.
     */
    public function getCalendarData(int $year): array
    {
        $teams = Team::with(['supporters' => function ($query) {
            $query->select('id', 'name', 'team_id');
        }, 'supporters.vacations' => function ($query) use ($year) {
            $query->where('year', $year)->orderBy('start_date', 'asc');
        }])->get();

        $formattedTeams = $teams->map(function ($team) {
            return [
                'id' => $team->id,
                'name' => $team->name,
                'shift' => $team->shift,
                'members' => $team->supporters->map(function ($member) {
                    $usedDays = $member->vacations->where('status', '!=', 'rejected')->sum('total_days');
                    
                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'vacation_summary' => [
                            'total_allowed' => 22,
                            'used_days' => $usedDays,
                            'remaining_days' => max(0, 22 - $usedDays),
                            'year' => $member->vacations->first()->year ?? Carbon::now()->year,
                        ],
                        'vacations' => $member->vacations->map(function ($vacation) {
                            return [
                                'id' => $vacation->id,
                                'start_date' => $vacation->start_date->toDateString(),
                                'end_date' => $vacation->end_date->toDateString(),
                                'total_days' => $vacation->total_days,
                                'status' => $vacation->status,
                            ];
                        })->values(),
                    ];
                })->values(),
            ];
        });

        return ['teams' => $formattedTeams];
    }
}