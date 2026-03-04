<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkSessionReportController extends Controller
{
    /**
     * Display a calendar/list view of work sessions.
     * Supporters see their own, Admins can filter by supporter.
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

        $query = WorkSession::with('user:id,name,email')
            ->withCount('pauses')
            ->latest('started_at');

        // Isolation: Supporters can only see themselves
        if ($user->isSupporter()) {
            $query->where('user_id', $user->id);
        }

        // Admin filters
        if ($user->isAdmin() && $request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('started_at', $request->input('date'));
        }

        $sessions = $query->paginate(15)->withQueryString();

        // Map data to display hours and format naturally for the frontend
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

        // Provide a list of supporters if the user is an admin (for the filter dropdown)
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
        ]);
    }
}