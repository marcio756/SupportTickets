<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Enums\WorkSessionStatusEnum;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates the core business logic for time tracking and shift management.
 */
class WorkSessionService
{
    /**
     * Starts a new work session for the given user.
     * Enforces the "One Shift Per Day" rule to give purpose to the Pause feature.
     *
     * @param User $user
     * @return WorkSession
     * @throws ValidationException
     */
    public function startSession(User $user): WorkSession
    {
        $openSession = WorkSession::where('user_id', $user->id)
            ->whereIn('status', [WorkSessionStatusEnum::ACTIVE->value, WorkSessionStatusEnum::PAUSED->value])
            ->first();

        if ($openSession) {
            throw ValidationException::withMessages([
                'status' => __('work_sessions.already_active')
            ]);
        }

        $alreadyCompletedToday = WorkSession::where('user_id', $user->id)
            ->where('status', WorkSessionStatusEnum::COMPLETED->value)
            ->whereDate('started_at', Carbon::today())
            ->exists();

        if ($alreadyCompletedToday) {
            throw ValidationException::withMessages([
                'status' => __('work_sessions.already_completed')
            ]);
        }

        return WorkSession::create([
            'user_id' => $user->id,
            'status' => WorkSessionStatusEnum::ACTIVE,
            'started_at' => now(),
        ]);
    }

    /**
     * Pauses the currently active session.
     *
     * @param User $user
     * @return WorkSession
     */
    public function pauseSession(User $user): WorkSession
    {
        $session = $this->getCurrentActiveSession($user);

        if ($session->status === WorkSessionStatusEnum::PAUSED) {
            throw ValidationException::withMessages(['status' => __('work_sessions.already_paused')]);
        }

        $session->update(['status' => WorkSessionStatusEnum::PAUSED]);
        
        $session->pauses()->create([
            'started_at' => now(),
        ]);

        return $session;
    }

    /**
     * Resumes a previously paused session.
     *
     * @param User $user
     * @return WorkSession
     */
    public function resumeSession(User $user): WorkSession
    {
        $session = WorkSession::where('user_id', $user->id)
            ->where('status', WorkSessionStatusEnum::PAUSED->value)
            ->first();

        if (!$session) {
            throw ValidationException::withMessages(['status' => __('work_sessions.no_paused_session')]);
        }

        $openPause = $session->pauses()->whereNull('ended_at')->latest('started_at')->first();
        if ($openPause) {
            $openPause->update(['ended_at' => now()]);
        }

        $session->update(['status' => WorkSessionStatusEnum::ACTIVE]);

        return $session;
    }

    /**
     * Ends the current session and finalizes calculation.
     *
     * @param User $user
     * @return WorkSession
     */
    public function endSession(User $user): WorkSession
    {
        $session = WorkSession::with('pauses')
            ->where('user_id', $user->id)
            ->whereIn('status', [WorkSessionStatusEnum::ACTIVE->value, WorkSessionStatusEnum::PAUSED->value])
            ->first();

        if (!$session) {
            throw ValidationException::withMessages(['status' => __('work_sessions.no_active_shift')]);
        }

        $now = now();

        if ($session->status === WorkSessionStatusEnum::PAUSED) {
            $openPause = $session->pauses()->whereNull('ended_at')->latest('started_at')->first();
            if ($openPause) {
                $openPause->update(['ended_at' => $now]);
            }
        }

        $totalPauseSeconds = $session->pauses->sum(function ($pause) use ($now) {
            $end = $pause->ended_at ?? $now;
            return $pause->started_at->diffInSeconds($end);
        });

        $grossSeconds = $session->started_at->diffInSeconds($now);
        $netSeconds = max(0, $grossSeconds - $totalPauseSeconds);

        $session->update([
            'status' => WorkSessionStatusEnum::COMPLETED,
            'ended_at' => $now,
            'total_worked_seconds' => $netSeconds,
        ]);

        return $session;
    }

    /**
     * Retrieves and formats work session reports based on user permissions and filters.
     * Includes pause data for calendar rendering. Does not paginate to ensure the calendar renders completely.
     *
     * @param User $user
     * @param array $filters
     * @return array
     */
    public function getReportsData(User $user, array $filters): array
    {
        $query = WorkSession::with(['user:id,name,email', 'pauses'])
            ->withCount('pauses')
            ->latest('started_at');

        if ($user->isSupporter()) {
            $query->where('user_id', $user->id);
        }

        if ($user->isAdmin() && !empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['week_start'])) {
            $start = Carbon::parse($filters['week_start'])->startOfDay();
            $end = $start->copy()->addDays(6)->endOfDay();
            $query->whereBetween('started_at', [$start, $end]);
        } elseif (!empty($filters['date'])) {
            $query->whereDate('started_at', $filters['date']);
        }

        $totalSeconds = (clone $query)
            ->where('status', WorkSessionStatusEnum::COMPLETED->value)
            ->sum('total_worked_seconds');
        
        $sessions = $query->get();

        $sessions->transform(function ($session) {
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
                'pauses' => $session->pauses->map(function ($pause) {
                    return [
                        'started_at' => $pause->started_at->format('H:i'),
                        'ended_at' => $pause->ended_at ? $pause->ended_at->format('H:i') : null,
                    ];
                })->toArray(),
                'total_time_formatted' => $session->total_worked_seconds ? "{$hours}h {$minutes}m" : '-',
            ];
        });

        $usersList = [];
        if ($user->isAdmin()) {
            $usersList = User::whereIn('role', [RoleEnum::SUPPORTER->value, RoleEnum::ADMIN->value])
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        }

        return [
            'sessions' => $sessions->toArray(),
            'users' => $usersList,
            'filters' => [
                'user_id' => $filters['user_id'] ?? null,
                'week_start' => $filters['week_start'] ?? null,
                'date' => $filters['date'] ?? null,
            ],
            'summary' => [
                'total_hours' => floor($totalSeconds / 3600),
                'total_minutes' => floor(($totalSeconds % 3600) / 60),
            ]
        ];
    }

    /**
     * Deletes a work session and logs the administrative action for audit purposes.
     *
     * @param WorkSession $session
     * @return void
     */
    public function deleteSession(WorkSession $session): void
    {
        DB::transaction(function () use ($session) {
            if (function_exists('activity')) {
                activity()
                    ->performedOn($session)
                    ->event('deleted')
                    ->withProperties([
                        'old' => [
                            'user_name' => $session->user?->name,
                            'started_at' => $session->started_at->toDateString(),
                            'total_seconds' => $session->total_worked_seconds
                        ]
                    ])
                    ->log(__('work_sessions.log_deleted'));
            }

            $session->delete();
        });
    }

    /**
     * Retrieves the current open session for a user.
     *
     * @param User $user
     * @return WorkSession
     */
    private function getCurrentActiveSession(User $user): WorkSession
    {
        $session = WorkSession::where('user_id', $user->id)
            ->whereIn('status', [WorkSessionStatusEnum::ACTIVE->value, WorkSessionStatusEnum::PAUSED->value])
            ->first();

        if (!$session) {
            throw ValidationException::withMessages(['status' => __('work_sessions.not_active')]);
        }

        return $session;
    }
}