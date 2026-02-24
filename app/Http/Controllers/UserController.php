<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     * Includes basic filtering by name/email and role.
     * * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        // Garante que só utilizadores Supporter ou Admin podem aceder
        if (!$request->user()->isSupporter()) {
            abort(403, 'Unauthorized access.');
        }

        $query = User::query();

        if ($request->filled('query')) {
            $searchTerm = '%' . $request->input('query') . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  ->orWhere('email', 'like', $searchTerm);
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        $users = $query->paginate(10)->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['query', 'role']),
            'roles' => [RoleEnum::CUSTOMER->value, RoleEnum::SUPPORTER->value],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')],
            'role' => ['required', Rule::enum(RoleEnum::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'password' => Hash::make($request->password),
        ]);

        return redirect()->back()->with('success', 'User created successfully.');
    }

    /**
     * Update the specified resource in storage.
     * * @param Request $request
     * @param User $user
     * @return RedirectResponse
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::enum(RoleEnum::class)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        // Fix: Use SUPPORTER instead of SUPPORT
        if ($user->role === RoleEnum::SUPPORTER && $request->role !== RoleEnum::SUPPORTER) {
            $supportCount = User::where('role', RoleEnum::SUPPORTER)->count();
            if ($supportCount <= 1) {
                return back()->withErrors(['role' => 'Cannot change the role of the last support user.']);
            }
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     * * @param Request $request
     * @param User $user
     * @return RedirectResponse
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        if ($request->user()->id === $user->id) {
            return back()->withErrors(['current_password' => 'You cannot delete your own account.']);
        }

        // Fix: Use SUPPORTER instead of SUPPORT
        if ($user->role === RoleEnum::SUPPORTER) {
            $supportCount = User::where('role', RoleEnum::SUPPORTER)->count();
            if ($supportCount <= 1) {
                return back()->withErrors(['current_password' => 'Cannot delete the last support user.']);
            }
        }

        $user->delete();

        return redirect()->back()->with('success', 'User deleted successfully.');
    }
}