<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FcmToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller responsible for handling the registration of FCM tokens for the authenticated user.
 */
class FcmTokenController extends Controller
{
    /**
     * Store or update an FCM token for the authenticated user.
     * Reassigns the token to the newly authenticated user if the device is shared.
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

        // Utilizamos o modelo diretamente para procurar pelo token na BD inteira.
        // Se o token já existir (mesmo que de outro utilizador), atualiza o 'user_id' para o utilizador atual.
        // Se não existir, cria um novo registo. Isto evita o erro 500 de Unique Constraint.
        FcmToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $user->id,
                'device_type' => $validated['device_type'] ?? 'unknown'
            ]
        );

        return response()->json([
            'message' => 'FCM Token registered successfully.',
        ], 200);
    }
}