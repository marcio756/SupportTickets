<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

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
}