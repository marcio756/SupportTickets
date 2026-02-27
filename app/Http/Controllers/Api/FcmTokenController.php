<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller responsible for handling the registration of FCM tokens for the authenticated user.
 */
class FcmTokenController extends Controller
{
    /**
     * Store or update an FCM token for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'device_type' => 'nullable|string|max:50',
        ]);

        $user = $request->user();

        // Use updateOrCreate to prevent duplicate tokens for the same user
        $user->fcmTokens()->updateOrCreate(
            ['token' => $validated['token']],
            ['device_type' => $validated['device_type'] ?? 'unknown']
        );

        return response()->json([
            'message' => 'FCM Token registered successfully.',
        ], 200);
    }
}