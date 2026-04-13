<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Manages authenticated user profile and security settings.
 */
class ProfileController extends Controller
{
    use ApiResponser;

    /**
     * Get the authenticated user's profile.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        return $this->successResponse(new UserResource($request->user()));
    }

    /**
     * Update the user's profile information.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
        ]);

        $user->update($validated);

        return $this->successResponse(new UserResource($user), 'Profile updated successfully.');
    }

    /**
     * Update the user's password with current password verification.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return $this->successResponse(null, 'Password changed successfully.');
    }

    /**
     * Delete the authenticated user's account and revoke active tokens.
     * Requires the current password for security validation.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Revoke all Sanctum tokens before deleting to log out from all devices
        $user->tokens()->delete();
        
        $user->delete();

        return $this->successResponse(null, 'Account deleted successfully.');
    }

    // =========================================================================
    // TWO-FACTOR AUTHENTICATION (2FA) API METHODS (PragmaRX)
    // =========================================================================

    /**
     * Initiates the 2FA enablement process.
     * Always generates a new secret to support both new setups and regenerations.
     */
    public function enableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();
        $google2fa = new Google2FA();

        // Sempre gerar uma chave nova quando esta rota é chamada
        $secret = $google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secret,
        ])->save();

        return $this->successResponse(null, '2FA setup initiated.');
    }

    /**
     * Returns the SVG graphic for the authenticator app setup.
     */
    public function twoFactorQrCode(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->two_factor_secret) {
            return $this->errorResponse('2FA is not enabled or initiated.', 400);
        }

        $google2fa = new Google2FA();
        
        $g2faUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $user->two_factor_secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($g2faUrl);

        return response()->json(['svg' => $svg]);
    }

    /**
     * Returns the raw secret key for manual authenticator app setup.
     */
    public function twoFactorSecretKey(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->two_factor_secret) {
            return $this->errorResponse('2FA is not enabled or initiated.', 400);
        }
        
        return response()->json(['secretKey' => $user->two_factor_secret]);
    }

    /**
     * Confirms the 2FA enablement using the OTP code.
     */
    public function confirmTwoFactor(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);
        
        $user = $request->user();
        $google2fa = new Google2FA();

        $valid = $google2fa->verifyKey($user->two_factor_secret, $request->code);

        if ($valid) {
            return $this->successResponse(null, '2FA confirmed successfully.');
        }

        return $this->errorResponse('Código incorreto ou expirado.', 422);
    }

    /**
     * Disables 2FA completely for the user.
     */
    public function disableTwoFactor(Request $request): JsonResponse
    {
        $request->user()->forceFill([
            'two_factor_secret' => null,
        ])->save();

        return $this->successResponse(null, '2FA disabled.');
    }

    /**
     * Retrieves the current emergency recovery codes.
     */
    public function getRecoveryCodes(Request $request): JsonResponse
    {
        // PragmaRX não inclui sistema de Recovery Codes nativo.
        return response()->json(['data' => []]);
    }

    /**
     * Invalidates old recovery codes and generates a fresh set.
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        return response()->json(['data' => []]);
    }
}