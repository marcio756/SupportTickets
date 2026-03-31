<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WorkSessionReportController extends Controller
{
    public function index(Request $request): Response
    {
        try {
            // Prevenção de segurança para Fatal Errors derivados de Memory Leaks de dados pesados
            ini_set('memory_limit', '512M');
            
            $user = $request->user();

            if (!$user || $user->isCustomer()) {
                abort(403, 'Unauthorized access.');
            }

            $weekStartInput = $request->input('week_start');
            if (empty($weekStartInput) || $weekStartInput === 'null' || $weekStartInput === 'undefined') {
                $weekStartInput = Carbon::now()->startOfWeek()->toDateString();
            }

            try {
                $weekStart = Carbon::parse($weekStartInput)->startOfDay();
            } catch (\Exception $e) {
                $weekStart = Carbon::now()->startOfWeek()->startOfDay();
            }
            
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

            // Limitado a 500 para evitar bloqueios no servidor e crashes
            $sessionsData = $query->limit(500)->get();
            $totalSeconds = 0;
            $transformedSessions = [];

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
                        return max(0, $pauseStart->diffInSeconds($pauseEnd));
                    });

                    $secs = (int) max(0, $grossSeconds - $pausedSeconds);
                    $totalSeconds += $secs;

                    $hours = floor($secs / 3600);
                    $minutes = floor(($secs % 3600) / 60);
                    
                    $statusValue = $session->status instanceof \BackedEnum ? $session->status->value : (string) $session->status;
                    
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
                    try { logger()->warning('Erro ao formatar sessão: ' . $err->getMessage()); } catch (\Throwable $logErr) {}
                    continue;
                }
            }

            $usersList = [];
            if ($user->isAdmin()) {
                $usersList = User::whereIn('role', [
                        RoleEnum::SUPPORTER->value ?? 'supporter', 
                        RoleEnum::ADMIN->value ?? 'admin'
                    ])
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
            // Garante que não disparamos 500 quando apenas o utilizador não tem autorização
            if ($e instanceof HttpException) throw $e;

            try { logger()->error('CRITICAL: WorkSessionReportController global falhou: ' . $e->getMessage()); } catch (\Throwable $logErr) {}
            
            return Inertia::render('WorkSessions/Index', [
                'sessions' => [],
                'users' => [],
                'filters' => ['user_id' => null, 'week_start' => null],
                'summary' => ['total_hours' => 0, 'total_minutes' => 0]
            ]);
        }
    }
}