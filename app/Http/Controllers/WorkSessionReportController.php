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
     * Calculates dynamically both COMPLETED and ACTIVE sessions.
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
        
        // CORREÇÃO: Utilizar copy() para criar uma nova instância e evitar mutar o $weekStart original
        $weekEnd = $weekStart->copy()->endOfWeek()->endOfDay();

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

        // ==========================================
        // CÁLCULO MATEMÁTICO AVANÇADO (INCLUI SESSÕES ATIVAS)
        // ==========================================
        $totalSeconds = 0;

        foreach ($sessionsData as $session) {
            if ($session->status === WorkSessionStatusEnum::COMPLETED && $session->total_worked_seconds !== null) {
                $totalSeconds += $session->total_worked_seconds;
            } else {
                // Cálculo dinâmico para sessões em curso (Ativa/Pausada)
                $start = Carbon::parse($session->started_at);
                $end = $session->ended_at ? Carbon::parse($session->ended_at) : Carbon::now();
                $elapsed = $start->diffInSeconds($end);

                $pauseSeconds = 0;
                if ($session->pauses) {
                    foreach ($session->pauses as $pause) {
                        $pStart = Carbon::parse($pause->started_at);
                        $pEnd = $pause->ended_at ? Carbon::parse($pause->ended_at) : Carbon::now();
                        $pauseSeconds += $pStart->diffInSeconds($pEnd);
                    }
                }

                $totalSeconds += max(0, $elapsed - $pauseSeconds);
            }
        }

        // Transform data for the calendar presentation
        $transformedSessions = $sessionsData->map(function ($session) {
            // Replicar o cálculo para o próprio bloco do evento (caso o frontend precise)
            if ($session->status === WorkSessionStatusEnum::COMPLETED && $session->total_worked_seconds !== null) {
                $secs = $session->total_worked_seconds;
            } else {
                $start = Carbon::parse($session->started_at);
                $end = $session->ended_at ? Carbon::parse($session->ended_at) : Carbon::now();
                $el = $start->diffInSeconds($end);
                
                $pSecs = 0;
                if ($session->pauses) {
                    foreach ($session->pauses as $p) {
                        $pS = Carbon::parse($p->started_at);
                        $pE = $p->ended_at ? Carbon::parse($p->ended_at) : Carbon::now();
                        $pSecs += $pS->diffInSeconds($pE);
                    }
                }
                $secs = max(0, $el - $pSecs);
            }

            $hours = floor($secs / 3600);
            $minutes = floor(($secs % 3600) / 60);
            
            return [
                'id' => $session->id,
                'user' => $session->user,
                'status' => $session->status->value,
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