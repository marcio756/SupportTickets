<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request to set the application locale.
     * This ensures validation messages, emails, and backend logic 
     * use the language selected by the user.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Session::has('locale')) {
            App::setLocale(Session::get('locale'));
        } else {
            // Fallback to the user's browser language if supported, otherwise config default
            $preferred = $request->getPreferredLanguage(['pt', 'en']);
            App::setLocale($preferred ?: config('app.locale'));
        }

        return $next($request);
    }
}