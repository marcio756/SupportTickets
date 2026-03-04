<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\WorkSession;
use App\Enums\WorkSessionStatusEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }

    /**
     * Intercepts logout to enforce work session rules.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

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