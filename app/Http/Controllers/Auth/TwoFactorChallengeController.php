<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorChallengeController extends Controller
{
    /**
     * Mostra o ecrã para introduzir o código 2FA.
     */
    public function create(Request $request)
    {
        // Se a sessão temporária não existir, expulsa para o login normal
        if (!$request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    /**
     * Verifica o código 2FA inserido e aprova/rejeita o login real.
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $userId = $request->session()->get('login.id');

        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::findOrFail($userId);
        $google2fa = new Google2FA();

        // Verifica o código usando a chave encriptada do utilizador
        $valid = $google2fa->verifyKey($user->two_factor_secret, $request->code);

        if ($valid) {
            // Sucesso! Fazer login real e limpar os dados temporários
            Auth::login($user, $request->session()->get('login.remember', false));
            $request->session()->forget(['login.id', 'login.remember']);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        // Falha na validação, regressa com erro
        return back()->withErrors(['code' => 'O código de autenticação fornecido é inválido.']);
    }
}