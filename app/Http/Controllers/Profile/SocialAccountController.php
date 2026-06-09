<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class SocialAccountController extends Controller
{
    /**
     * Redirects the authenticated user to the OAuth provider to link the account.
     *
     * @param string $provider
     * @return \Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirect(string $provider)
    {
        // Substitui a URI padrão do .env para garantir que a Google/Facebook
        // redireciona de volta para a zona autenticada do Perfil.
        return Socialite::driver($provider)
            ->redirectUrl(route('profile.social.callback', ['provider' => $provider]))
            ->redirect();
    }

    /**
     * Handles the callback and links the provider ID to the authenticated user.
     *
     * @param Request $request
     * @param string $provider
     * @return RedirectResponse
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        try {
            // É obrigatório manter o mesmo redirectUrl usado no pedido original
            // caso contrário o provider (ex: Google) rejeitará a validação por segurança.
            $socialUser = Socialite::driver($provider)
                ->redirectUrl(route('profile.social.callback', ['provider' => $provider]))
                ->user();
        } catch (\Exception $e) {
            return redirect()->route('profile.edit')->with('status', 'erro-associacao-social');
        }

        $providerField = $provider . '_id';

        // Security Check: Ensure this social account isn't already linked to someone else's profile.
        $isLinkedToAnotherUser = User::where($providerField, $socialUser->getId())
            ->where('id', '!=', $request->user()->id)
            ->exists();

        if ($isLinkedToAnotherUser) {
            return redirect()->route('profile.edit')->withErrors([
                'social' => 'Esta conta ' . ucfirst($provider) . ' já está associada a outro utilizador do sistema.',
            ]);
        }

        // Apply the link to the current authenticated user.
        $request->user()->update([
            $providerField => $socialUser->getId(),
        ]);

        return redirect()->route('profile.edit')->with('status', 'social-linked');
    }

    /**
     * Unlinks the specific social provider from the authenticated user's account.
     *
     * @param Request $request
     * @param string $provider
     * @return RedirectResponse
     */
    public function destroy(Request $request, string $provider): RedirectResponse
    {
        $providerField = $provider . '_id';

        $request->user()->update([
            $providerField => null,
        ]);

        return redirect()->route('profile.edit')->with('status', 'social-unlinked');
    }
}