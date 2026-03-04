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
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'You must start your work session (clock in) to perform this action.'], 403);
                }

                // Instead of a hard 403, we gracefully redirect back with a flash message 
                // that Inertia can catch and display as a nice notification.
                return redirect()->back()->with('error', 'Action denied: You must start your work session (clock in) first.');
            }
        }

        return $next($request);
    }
}