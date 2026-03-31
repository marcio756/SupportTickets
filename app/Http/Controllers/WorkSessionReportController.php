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
     * Architect Note: Enclosed in a bulletproof try-catch sequence to absolutely 
     * prevent any TypeErrors or Carbon parsing exceptions from bubbling up and 
     * causing a 500 white-screen crash on the frontend.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        try {
            $user = $request->user();

            if ($user->isCustomer()) {
                abort(403, 'Unauthorized access.');
            }

            // Fallback safe date parsing
            $weekStartInput = $request->input('week_start');
            if (empty($weekStartInput) || $weekStartInput === 'null' || $weekStartInput === 'undefined') {
                $weekStartInput = Carbon::now()->startOfWeek()->toDateString();
            }

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

            if ($user->isAdmin() && $request->filled('user_id') && $request->input('user_id') !== 'null') {
                $query->where('user_id', $request->input('user_id'));
            }

            $sessionsData = $query->get();
            $totalSeconds = 0;
            $transformedSessions = [];

            // Utilizar loop normal em vez de map() melhora a legibilidade e permite usar continue de forma limpa.
            foreach ($sessionsData as $session) {
                if (empty($session->started_at)) continue;

                try {
                    $startedAt = Carbon::parse($session->started_at);
                    $endedAt = $session->ended_at ? Carbon::parse($session->ended_at) : now();

                    $grossSeconds = $startedAt->diffInSeconds($endedAt);
                    
                    $pauses = $session->pauses ?? collect();
                    $pausedSeconds = $pauses->sum(function ($pause) {
                        if (empty($pause->started_at)) return 0;
                        
                        $pauseStart = Carbon::parse($pause->started_at);
                        $pauseEnd = $pause->ended_at ? Carbon::parse($pause->ended_at) : now();
                        return $pauseStart->diffInSeconds($pauseEnd);
                    });

                    $secs = max(0, $grossSeconds - $pausedSeconds);
                    $totalSeconds += $secs;

                    $hours = floor($secs / 3600);
                    $minutes = floor(($secs % 3600) / 60);
                    
                    $statusValue = 'unknown';
                    if ($session->status instanceof \BackedEnum) {
                        $statusValue = $session->status->value;
                    } elseif (is_string($session->status)) {
                        $statusValue = $session->status;
                    }
                    
                    $transformedPauses = [];
                    foreach ($pauses as $pause) {
                        if (empty($pause->started_at)) continue;
                        $transformedPauses[] = [
                            'id' => $pause->id,
                            'started_at_iso' => Carbon::parse($pause->started_at)->toIso8601String(),
                            'ended_at_iso' => $pause->ended_at ? Carbon::parse($pause->ended_at)->toIso8601String() : null,
                        ];
                    }

                    $transformedSessions[] = [
                        'id' => $session->id,
                        'user' => $session->user ? [
                            'id' => $session->user->id,
                            'name' => $session->user->name,
                            'email' => $session->user->email,
                        ] : null,
                        'status' => $statusValue,
                        'started_at_iso' => $startedAt->toIso8601String(),
                        'ended_at_iso' => $session->ended_at ? Carbon::parse($session->ended_at)->toIso8601String() : null,
                        'total_time_formatted' => $secs > 0 ? "{$hours}h {$minutes}m" : '-',
                        'pauses' => $transformedPauses,
                    ];
                } catch (\Throwable $err) {
                    // Ignora silenciosamente esta sessão específica corrompida e continua o loop em vez de rebentar tudo.
                    logger()->warning('Erro ao formatar WorkSession no relatório: ' . $err->getMessage());
                    continue;
                }
            }

            $usersList = [];
            if ($user->isAdmin()) {
                $usersList = User::whereIn('role', [RoleEnum::SUPPORTER->value, RoleEnum::ADMIN->value])
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get()
                    ->toArray();
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

        } catch (\Throwable $e) {
            logger()->error('CRITICAL: WorkSessionReportController global falhou: ' . $e->getMessage());
            
            // Retorna a página mesmo se houver catástrofe com os dados, assim o frontend nunca mais devolve ecrã branco.
            return Inertia::render('WorkSessions/Index', [
                'sessions' => [],
                'users' => [],
                'filters' => ['user_id' => null, 'week_start' => null],
                'summary' => ['total_hours' => 0, 'total_minutes' => 0]
            ]);
        }
    }
}