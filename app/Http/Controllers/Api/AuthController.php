<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA; // Importação obrigatória para o 2FA

/**
 * Handle authentication for mobile/external API clients
 */
class AuthController extends Controller
{
    use ApiResponser;

    /**
     * Authenticate user and return an API token.
     * Intercepts the request if 2FA is enabled.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'device_name' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais fornecidas estão incorretas.'],
            ]);
        }

        // Interceção 2FA: Se o utilizador tiver a chave secreta gerada, bloqueia o token.
        if (!empty($user->two_factor_secret)) {
            return response()->json([
                'two_factor' => true,
                'message' => 'Por favor, insira o código da sua aplicação Authenticator.',
                'email' => $user->email // Devolvemos o email para o Challenge subsequente
            ]);
        }

        // Processo normal sem 2FA: Generate a new token for the specific device
        $token = $user->createToken($request->device_name)->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ], 'Autenticação realizada com sucesso.');
    }

    /**
     * Desafio 2FA. Recebe o código OTP e o email, verifica com o PragmaRX e emite o token.
     * * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function twoFactorChallenge(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string'],
            'device_name' => ['required'], // Necessário para criar o token final
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || empty($user->two_factor_secret)) {
            return $this->errorResponse('Acesso inválido ou 2FA não configurado.', 400);
        }

        // Validação criptográfica do código
        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user->two_factor_secret, $request->code);

        if (!$valid) {
            throw ValidationException::withMessages([
                'code' => ['Código de autenticação incorreto ou expirado.'],
            ]);
        }

        // Sucesso 2FA - Emitir Token Definitivo
        $token = $user->createToken($request->device_name)->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ], 'Autenticação 2FA bem sucedida.');
    }

    /**
     * Revoke the current access token
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Token revogado com sucesso.');
    }

    /**
     * Send a password reset link/token to the given user's email.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::broker()->sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return $this->successResponse(null, __($status));
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    /**
     * Reset the user's password using the provided token.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->successResponse(null, __($status));
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }
}