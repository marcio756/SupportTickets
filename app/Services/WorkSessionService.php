<?php

namespace App\Services;

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
                'status' => 'You already have an active work session.'
            ]);
        }

        $alreadyCompletedToday = WorkSession::where('user_id', $user->id)
            ->where('status', WorkSessionStatusEnum::COMPLETED->value)
            ->whereDate('started_at', Carbon::today())
            ->exists();

        if ($alreadyCompletedToday) {
            throw ValidationException::withMessages([
                'status' => 'You have already completed your shift for today. Please wait until tomorrow to start a new shift.'
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
            throw ValidationException::withMessages(['status' => 'Your shift is already paused.']);
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
            throw ValidationException::withMessages(['status' => 'No paused session was found to resume.']);
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
            throw ValidationException::withMessages(['status' => 'There is no active shift to end.']);
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
                    ->event('deleted') // Explicitly declare the event so it shows correctly in the UI badge
                    ->withProperties([
                        'old' => [ // Wrap in 'old' to simulate a standard Eloquent model deletion
                            'user_name' => $session->user?->name,
                            'started_at' => $session->started_at->toDateString(),
                            'total_seconds' => $session->total_worked_seconds
                        ]
                    ])
                    ->log("Work session manually deleted by administrator.");
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
            throw ValidationException::withMessages(['status' => 'You do not have an active work shift.']);
        }

        return $session;
    }
}