<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Redirects the user to the specified OAuth provider.
     *
     * @param string $provider
     * @return \Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirect(string $provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handles the callback from the OAuth provider.
     * Prevents account creation; only allows linking and login for existing emails.
     *
     * @param string $provider
     * @return RedirectResponse
     */
    public function callback(string $provider): RedirectResponse
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors([
                'email' => 'Ocorreu um erro ao comunicar com o fornecedor de autenticação.',
            ]);
        }

        // Enforce the rule: Account must already exist to proceed.
        $user = User::where('email', $socialUser->getEmail())->first();

        if (!$user) {
            return redirect()->route('login')->withErrors([
                'email' => 'Não existe nenhuma conta associada a este email. Por favor, utiliza as tuas credenciais de acesso ou pede a criação da tua conta.',
            ]);
        }

        // Automatically link the provider ID if it hasn't been linked yet
        $providerField = $provider . '_id';
        if (!$user->{$providerField}) {
            $user->update([
                $providerField => $socialUser->getId(),
            ]);
        }

        // Log the user in
        Auth::login($user);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}   