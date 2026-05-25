<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Inertia\Inertia;

/**
 * Middleware to ensure developers are strictly confined to the Pulse dashboard.
 * Prevents them from accessing operational parts of the application.
 */
class PreventDeveloperAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->isDeveloper()) {
            
            // Allow access strictly to the customized pulse routes and logout
            if ($request->is('pulse*') || $request->is('logout') || $request->is('language')) {
                return $next($request);
            }

            if ($request->header('X-Inertia')) {
                return Inertia::location('/pulse');
            }
            
            return redirect()->to('/pulse');
        }

        return $next($request);
    }
}