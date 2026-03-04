<?php

namespace App\Services;

use App\Enums\WorkSessionStatusEnum;
use App\Models\User;
use App\Models\WorkSession;
use App\Models\WorkSessionPause;
use Illuminate\Validation\ValidationException;

/**
 * Encapsulates the core business logic for time tracking and shift management.
 * Keeps controllers clean and ensures database state remains valid during transitions.
 */
class WorkSessionService
{
    /**
     * Starts a new work session for the given user.
     * Prevents overlapping open sessions.
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
                'status' => 'You already have an open work session. Please end it first.'
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
     * @throws ValidationException
     */
    public function pauseSession(User $user): WorkSession
    {
        $session = $this->getCurrentActiveSession($user);

        if ($session->status === WorkSessionStatusEnum::PAUSED) {
            throw ValidationException::withMessages(['status' => 'Session is already paused.']);
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
     * @throws ValidationException
     */
    public function resumeSession(User $user): WorkSession
    {
        $session = WorkSession::where('user_id', $user->id)
            ->where('status', WorkSessionStatusEnum::PAUSED->value)
            ->first();

        if (!$session) {
            throw ValidationException::withMessages(['status' => 'No paused session found to resume.']);
        }

        // Close the open pause record
        $openPause = $session->pauses()->whereNull('ended_at')->latest('started_at')->first();
        if ($openPause) {
            $openPause->update(['ended_at' => now()]);
        }

        $session->update(['status' => WorkSessionStatusEnum::ACTIVE]);

        return $session;
    }

    /**
     * Ends the current open session and calculates the true worked time.
     *
     * @param User $user
     * @return WorkSession
     * @throws ValidationException
     */
    public function endSession(User $user): WorkSession
    {
        $session = WorkSession::with('pauses')
            ->where('user_id', $user->id)
            ->whereIn('status', [WorkSessionStatusEnum::ACTIVE->value, WorkSessionStatusEnum::PAUSED->value])
            ->first();

        if (!$session) {
            throw ValidationException::withMessages(['status' => 'No open session found to end.']);
        }

        $now = now();

        // If ending while paused, close the pending pause first
        if ($session->status === WorkSessionStatusEnum::PAUSED) {
            $openPause = $session->pauses()->whereNull('ended_at')->latest('started_at')->first();
            if ($openPause) {
                $openPause->update(['ended_at' => $now]);
            }
        }

        // Calculate total pause time in seconds
        $totalPauseSeconds = $session->pauses->sum(function ($pause) use ($now) {
            $end = $pause->ended_at ?? $now;
            return $pause->started_at->diffInSeconds($end);
        });

        // Calculate total active time
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
     * Helper to reliably fetch the user's active session.
     */
    private function getCurrentActiveSession(User $user): WorkSession
    {
        $session = WorkSession::where('user_id', $user->id)
            ->whereIn('status', [WorkSessionStatusEnum::ACTIVE->value, WorkSessionStatusEnum::PAUSED->value])
            ->first();

        if (!$session) {
            throw ValidationException::withMessages(['status' => 'No active work session found.']);
        }

        return $session;
    }
}