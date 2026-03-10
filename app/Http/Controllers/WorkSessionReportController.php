<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Enums\WorkSessionStatusEnum;
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
     * Implements strict role-based data isolation and filtering.
     * * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Security gate: Customers should not see work logs
        if ($user->isCustomer()) {
            abort(403, 'Unauthorized access.');
        }

        // Determine the requested week or default to the current week
        $weekStartInput = $request->input('week_start', Carbon::now()->startOfWeek()->toDateString());
        $weekStart = Carbon::parse($weekStartInput)->startOfDay();
        $weekEnd = clone $weekStart->endOfWeek()->endOfDay();

        $query = WorkSession::with([
                'user:id,name,email', 
                'pauses' => function($q) {
                    $q->orderBy('started_at', 'asc');
                }
            ])
            ->whereBetween('started_at', [$weekStart, $weekEnd])
            ->latest('started_at');

        // Isolation Logic: Supporters only see their data
        if ($user->isSupporter()) {
            $query->where('user_id', $user->id);
        }

        // Admin Filter Logic
        if ($user->isAdmin() && $request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $sessionsData = $query->get();

        // Calculate aggregate summary for the filtered period
        $totalSeconds = $sessionsData
            ->where('status', WorkSessionStatusEnum::COMPLETED)
            ->sum('total_worked_seconds');

        // Transform data for the calendar presentation
        $transformedSessions = $sessionsData->map(function ($session) {
            $hours = $session->total_worked_seconds ? floor($session->total_worked_seconds / 3600) : 0;
            $minutes = $session->total_worked_seconds ? floor(($session->total_worked_seconds % 3600) / 60) : 0;
            
            return [
                'id' => $session->id,
                'user' => $session->user,
                'status' => $session->status->value,
                // We send exact ISO strings so the frontend can plot them on the calendar accurately
                'started_at_iso' => $session->started_at->toIso8601String(),
                'ended_at_iso' => $session->ended_at ? $session->ended_at->toIso8601String() : null,
                'total_time_formatted' => $session->total_worked_seconds ? "{$hours}h {$minutes}m" : '-',
                'pauses' => $session->pauses->map(function ($pause) {
                    return [
                        'id' => $pause->id,
                        'started_at_iso' => $pause->started_at->toIso8601String(),
                        'ended_at_iso' => $pause->ended_at ? $pause->ended_at->toIso8601String() : null,
                    ];
                }),
            ];
        });

        // Provide list of supporters to Admin for filtering
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