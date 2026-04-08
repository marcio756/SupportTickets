<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\WorkSession;
use App\Enums\WorkSessionStatusEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();

        // Se o utilizador tem 2FA configurado, interceptamos o login
        if ($user && !empty($user->two_factor_secret)) {
            // Deslogar imediatamente
            Auth::guard('web')->logout();

            // Guardar explicitamente o ID do utilizador na sessão temporária
            $request->session()->put('login.id', $user->id);
            $request->session()->put('login.remember', $request->boolean('remember'));

            // FORÇAR a gravação da sessão agora mesmo para evitar que se perca no redirecionamento
            $request->session()->save();

            return redirect()->route('two-factor.challenge');
        }

        // Fluxo normal para quem não tem 2FA
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Destroy an authenticated session.
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