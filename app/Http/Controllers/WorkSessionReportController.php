<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Enums\WorkSessionStatusEnum;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkSessionReportController extends Controller
{
    /**
     * Renders the work session log with summary statistics.
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

        $query = WorkSession::with('user:id,name,email')
            ->withCount('pauses')
            ->latest('started_at');

        // Isolation Logic: Supporters only see their data
        if ($user->isSupporter()) {
            $query->where('user_id', $user->id);
        }

        // Admin Filter Logic
        if ($user->isAdmin() && $request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // Date Filter Logic
        if ($request->filled('date')) {
            $query->whereDate('started_at', $request->input('date'));
        }

        // Calculate aggregate summary for the filtered period
        // We clone the query to calculate totals without pagination limits
        $totalSeconds = (clone $query)
            ->where('status', WorkSessionStatusEnum::COMPLETED)
            ->sum('total_worked_seconds');
        
        $sessions = $query->paginate(15)->withQueryString();

        // Transform data for presentation
        $sessions->getCollection()->transform(function ($session) {
            $hours = $session->total_worked_seconds ? floor($session->total_worked_seconds / 3600) : 0;
            $minutes = $session->total_worked_seconds ? floor(($session->total_worked_seconds % 3600) / 60) : 0;
            
            return [
                'id' => $session->id,
                'user' => $session->user,
                'status' => $session->status,
                'date' => $session->started_at->format('Y-m-d'),
                'started_at' => $session->started_at->format('H:i'),
                'ended_at' => $session->ended_at ? $session->ended_at->format('H:i') : null,
                'pauses_count' => $session->pauses_count,
                'total_time_formatted' => $session->total_worked_seconds ? "{$hours}h {$minutes}m" : '-',
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
            'sessions' => $sessions,
            'users' => $usersList,
            'filters' => $request->only(['user_id', 'date']),
            'summary' => [
                'total_hours' => floor($totalSeconds / 3600),
                'total_minutes' => floor(($totalSeconds % 3600) / 60),
            ]
        ]);
    }
}