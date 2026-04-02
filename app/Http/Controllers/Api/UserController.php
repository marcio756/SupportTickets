<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Handles user management operations including listing, creating,
 * updating, soft-deleting, and restoring users.
 */
class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     * Includes basic filtering by name/email and role.
     * Supporters and Admins can view this list.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (!$request->user()->isSupporter() && (!$request->user()->isAdmin())) {
                return response()->json(['message' => 'Unauthorized access.'], 403);
            }

            // Include soft-deleted users so the frontend can display and restore them
            $query = User::withTrashed();

            // Aceita 'search' (usado pelo CustomerSelector) ou 'query'
            $searchTermInput = $request->input('search', $request->input('query'));

            if (!empty($searchTermInput)) {
                $searchTerm = '%' . $searchTermInput . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'like', $searchTerm)
                      ->orWhere('email', 'like', $searchTerm);
                });
            }

            if ($request->filled('role') && $request->input('role') !== 'all') {
                $query->where('role', $request->input('role'));
            }

            // Conversão estrita para Inteiro garantida
            $limit = $request->integer('limit', 20);
            
            // Architect Note: Substituído paginate() por simplePaginate()
            // para suportar milhões de clientes sem executar um SELECT COUNT(*) lento na BD.
            $users = $query->latest('id')->simplePaginate($limit);

            return response()->json($users);

        } catch (\Throwable $e) {
            // Architect Note: Intercetamos a falha para enviar detalhes precisos para o frontend
            // Isto vai revelar de imediato se o problema é uma migração em falta ou um erro de SQL.
            return response()->json([
                'message' => 'Internal Server Error',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     * Strictly limited to administrators.
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')],
            'role' => ['required', Rule::enum(RoleEnum::class)],
            'password' => ['required', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json(['message' => 'User created successfully.', 'data' => $user], 201);
    }

    /**
     * Update the specified resource in storage.
     * Strictly limited to administrators.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::enum(RoleEnum::class)],
            'password' => ['nullable', Password::defaults()],
        ]);

        // Prevent changing the role of the last remaining admin
        $currentRole = $user->role instanceof RoleEnum ? $user->role->value : $user->role;
        if ($currentRole === RoleEnum::ADMIN->value && $request->role !== RoleEnum::ADMIN->value) {
            $adminCount = User::where('role', RoleEnum::ADMIN->value)->count();
            if ($adminCount <= 1) {
                return response()->json(['message' => 'Cannot change the role of the last admin user.'], 422);
            }
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
        ]);

        if (!empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        return response()->json(['message' => 'User updated successfully.', 'data' => $user]);
    }

    /**
     * Remove (deactivate) the specified resource from storage.
     * Strictly limited to administrators.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        // Prevent an admin from deleting the last admin in the system
        $currentRole = $user->role instanceof RoleEnum ? $user->role->value : $user->role;
        if ($currentRole === RoleEnum::ADMIN->value) {
            $adminCount = User::where('role', RoleEnum::ADMIN->value)->count();
            if ($adminCount <= 1) {
                return response()->json(['message' => 'Cannot deactivate the last admin user.'], 422);
            }
        }

        $user->delete();

        return response()->json(['message' => 'User deactivated successfully.']);
    }

    /**
     * Restore a previously deactivated user account.
     * Strictly limited to administrators.
     *
     * @param Request $request
     * @param int|string $id The ID of the soft-deleted user
     */
    public function restore(Request $request, $id): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        // Retrieve the user even if they are soft-deleted
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        return response()->json(['message' => 'User restored successfully.', 'data' => $user]);
    }
}