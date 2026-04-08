<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\WorkSession;
use App\Enums\WorkSessionStatusEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route; // Added to fix "Class Route not found" error
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     * Includes check for password reset route availability.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'), // Passes boolean to Vue frontend
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Intercetação do Desafio 2FA definido no LoginRequest
        if ($request->session()->has('login.id')) {
            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Destroy an authenticated session (Logout).
     * * Intercepts logout to enforce work session rules:
     * Supporters and Admins must end their active shifts before logging out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Enforce shift closure for Supporter/Admin roles
        if ($user && ($user->isSupporter() || $user->isAdmin())) {
            $activeShift = WorkSession::where('user_id', $user->id)
                ->whereIn('status', [
                    WorkSessionStatusEnum::ACTIVE->value, 
                    WorkSessionStatusEnum::PAUSED->value
                ])
                ->exists();

            if ($activeShift) {
                return redirect()->back()->withErrors([
                    'logout' => 'Impossível sair: Tem um turno de trabalho em curso. Termine o seu turno antes de encerrar a aplicação.'
                ]);
            }
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}