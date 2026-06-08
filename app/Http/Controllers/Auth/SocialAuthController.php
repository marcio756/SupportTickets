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
     * Redirects the guest user to the specified OAuth provider for login.
     *
     * @param string $provider
     * @return \Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirect(string $provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handles the callback from the OAuth provider for authentication.
     * Strictly enforces that the social ID must ALREADY be linked to an account.
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

        $providerField = $provider . '_id';

        // Enforce the rule: Account must already be explicitly linked via the Profile.
        // We match by the unique provider ID, not just the email, for strict security.
        $user = User::where($providerField, $socialUser->getId())->first();

        if (!$user) {
            return redirect()->route('login')->withErrors([
                'email' => 'Conta inexistente ou não associada. Por favor, faz login com as tuas credenciais e associa o ' . ucfirst($provider) . ' no teu Perfil.',
            ]);
        }

        // Log the user in
        Auth::login($user);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}