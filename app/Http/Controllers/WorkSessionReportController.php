<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class WorkSessionReportController extends Controller
{
    /**
     * Renders the work session log with summary statistics for a weekly calendar view.
     * Architect Note: Mathematical logic delegated to the Model layer via 
     * the 'total_duration_seconds' accessor to ensure DRY principles and prevent 500 errors.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if ($user->isCustomer()) {
            abort(403, 'Unauthorized access.');
        }

        $weekStartInput = $request->input('week_start', Carbon::now()->startOfWeek()->toDateString());
        $weekStart = Carbon::parse($weekStartInput)->startOfDay();
        $weekEnd = $weekStart->copy()->endOfWeek()->endOfDay();

        $query = WorkSession::with([
                'user:id,name,email', 
                'pauses' => function($q) {
                    $q->orderBy('started_at', 'asc');
                }
            ])
            ->whereBetween('started_at', [$weekStart, $weekEnd])
            ->latest('started_at');

        if ($user->isSupporter()) {
            $query->where('user_id', $user->id);
        }

        if ($user->isAdmin() && $request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $sessionsData = $query->get();

        $totalSeconds = 0;

        $transformedSessions = $sessionsData->map(function ($session) use (&$totalSeconds) {
            // Architect Note: Utilizing the pre-calculated logic from the Model.
            // This prevents complex loop calculations inside the controller and resolves
            // the 500 errors caused by incorrect Enum comparisons.
            $secs = $session->total_duration_seconds; 
            
            $totalSeconds += $secs;

            $hours = floor($secs / 3600);
            $minutes = floor(($secs % 3600) / 60);
            
            return [
                'id' => $session->id,
                'user' => $session->user,
                // Ensure safe access to the enum value
                'status' => $session->status ? $session->status->value : 'unknown',
                'started_at_iso' => $session->started_at->toIso8601String(),
                'ended_at_iso' => $session->ended_at ? $session->ended_at->toIso8601String() : null,
                'total_time_formatted' => $secs > 0 ? "{$hours}h {$minutes}m" : '-',
                'pauses' => $session->pauses->map(function ($pause) {
                    return [
                        'id' => $pause->id,
                        'started_at_iso' => $pause->started_at->toIso8601String(),
                        'ended_at_iso' => $pause->ended_at ? $pause->ended_at->toIso8601String() : null,
                    ];
                }),
            ];
        });

        $usersList = [];
        if ($user->isAdmin()) {
            $usersList = User::whereIn('role', [RoleEnum::SUPPORTER->value, RoleEnum::ADMIN->value])
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        }

        return Inertia::render('WorkSessions/Index', [
            'sessions' => $transformedSessions,
            'users' => $usersList,
            'filters' => [
                'user_id' => $request->input('user_id'),
                'week_start' => $weekStart->toDateString()
            ],
            'summary' => [
                'total_hours' => floor($totalSeconds / 3600),
                'total_minutes' => floor(($totalSeconds % 3600) / 60),
            ]
        ]);
    }
}