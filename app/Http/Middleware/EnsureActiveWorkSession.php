<?php

namespace App\Http\Middleware;

use App\Enums\WorkSessionStatusEnum;
use App\Models\WorkSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures that supporters have an active clock-in session before 
 * allowing them to interact with or resolve tickets.
 * Customers bypass this check automatically.
 */
class EnsureActiveWorkSession
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only enforce the rule for internal support staff
        if ($user && $user->isSupporter()) {
            $hasActiveSession = WorkSession::where('user_id', $user->id)
                ->where('status', WorkSessionStatusEnum::ACTIVE->value)
                ->exists();

            if (!$hasActiveSession) {
                abort(403, 'You must have an active work session (clocked-in) to manage tickets.');
            }
        }

        return $next($request);
    }
}