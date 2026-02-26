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

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     * Includes basic filtering by name/email and role.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->isSupporter()) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        $query = User::query();

        if ($request->filled('query')) {
            $searchTerm = '%' . $request->input('query') . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  ->orWhere('email', 'like', $searchTerm);
            });
        }

        if ($request->filled('role') && $request->input('role') !== 'all') {
            $query->where('role', $request->input('role'));
        }

        $users = $query->paginate(15);

        return response()->json($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->isSupporter()) {
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
     */
    public function update(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->isSupporter()) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::enum(RoleEnum::class)],
            'password' => ['nullable', Password::defaults()],
        ]);

        if ($user->role === RoleEnum::SUPPORTER && $request->role !== RoleEnum::SUPPORTER->value) {
            $supportCount = User::where('role', RoleEnum::SUPPORTER)->count();
            if ($supportCount <= 1) {
                return response()->json(['message' => 'Cannot change the role of the last support user.'], 422);
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
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->isSupporter()) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        if ($request->user()->id === $user->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        if ($user->role === RoleEnum::SUPPORTER) {
            $supportCount = User::where('role', RoleEnum::SUPPORTER)->count();
            if ($supportCount <= 1) {
                return response()->json(['message' => 'Cannot delete the last support user.'], 422);
            }
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }
}