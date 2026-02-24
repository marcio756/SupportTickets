<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    use ApiResponser;

    /**
     * Get the authenticated user's profile.
     */
    public function show(Request $request): JsonResponse
    {
        return $this->successResponse(new UserResource($request->user()));
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $user->update($validated);

        return $this->successResponse(new UserResource($user), 'Perfil atualizado com sucesso.');
    }
}