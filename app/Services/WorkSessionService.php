<?php

namespace App\Services;

use App\Enums\WorkSessionStatusEnum;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/**
 * Encapsulates the core business logic for time tracking and shift management.
 */
class WorkSessionService
{
    /**
     * Starts a new work session for the given user.
     * Enforces the "One Shift Per Day" rule to give purpose to the Pause feature.
     * * @param User $user
     * @return WorkSession
     * @throws ValidationException
     */
    public function startSession(User $user): WorkSession
    {
        // 1. Prevent concurrent open sessions
        $openSession = WorkSession::where('user_id', $user->id)
            ->whereIn('status', [WorkSessionStatusEnum::ACTIVE->value, WorkSessionStatusEnum::PAUSED->value])
            ->first();

        if ($openSession) {
            throw ValidationException::withMessages([
                'status' => 'Já tem uma sessão de trabalho aberta.'
            ]);
        }

        // 2. Enforce Daily Shift integrity
        $alreadyCompletedToday = WorkSession::where('user_id', $user->id)
            ->where('status', WorkSessionStatusEnum::COMPLETED->value)
            ->whereDate('started_at', Carbon::today())
            ->exists();

        if ($alreadyCompletedToday) {
            throw ValidationException::withMessages([
                'status' => 'Já concluiu o seu turno de hoje. Para manter relatórios limpos, utilize a "Pausa" em vez de "Terminar" se pretender regressar no mesmo dia.'
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
     */
    public function pauseSession(User $user): WorkSession
    {
        $session = $this->getCurrentActiveSession($user);

        if ($session->status === WorkSessionStatusEnum::PAUSED) {
            throw ValidationException::withMessages(['status' => 'A sessão já está em pausa.']);
        }

        $session->update(['status' => WorkSessionStatusEnum::PAUSED]);
        
        $session->pauses()->create([
            'started_at' => now(),
        ]);

        return $session;
    }

    /**
     * Resumes a previously paused session.
     */
    public function resumeSession(User $user): WorkSession
    {
        $session = WorkSession::where('user_id', $user->id)
            ->where('status', WorkSessionStatusEnum::PAUSED->value)
            ->first();

        if (!$session) {
            throw ValidationException::withMessages(['status' => 'Não foi encontrada nenhuma sessão em pausa para retomar.']);
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
     */
    public function endSession(User $user): WorkSession
    {
        $session = WorkSession::with('pauses')
            ->where('user_id', $user->id)
            ->whereIn('status', [WorkSessionStatusEnum::ACTIVE->value, WorkSessionStatusEnum::PAUSED->value])
            ->first();

        if (!$session) {
            throw ValidationException::withMessages(['status' => 'Não existe um turno aberto para terminar.']);
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

    private function getCurrentActiveSession(User $user): WorkSession
    {
        $session = WorkSession::where('user_id', $user->id)
            ->whereIn('status', [WorkSessionStatusEnum::ACTIVE->value, WorkSessionStatusEnum::PAUSED->value])
            ->first();

        if (!$session) {
            throw ValidationException::withMessages(['status' => 'Não tem um turno de trabalho ativo.']);
        }

        return $session;
    }
}