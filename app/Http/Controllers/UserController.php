<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     *
     * @param Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search']),
            'roles' => collect(RoleEnum::cases())->map(fn($role) => $role->value),
        ]);
    }

    /**
     * Store a newly created user in storage.
     * Requires the current user's password for security.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(RoleEnum::class)],
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return redirect()->back()->with('success', 'User created successfully.');
    }

    /**
     * Update the specified user in storage.
     * Prevents editing the role of the last support user.
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::enum(RoleEnum::class)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        // Guard clause: Prevent changing the role of the last remaining support user
        if ($user->role === RoleEnum::SUPPORT->value && $request->role !== RoleEnum::SUPPORT->value) {
            $supportCount = User::where('role', RoleEnum::SUPPORT->value)->count();
            if ($supportCount <= 1) {
                return back()->withErrors(['role' => 'Cannot change the role of the last support user.']);
            }
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
        ]);

        // Update password only if a new one was provided
        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified user from storage.
     * Prevents deleting the last support user or the currently authenticated user.
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, User $user)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        // Guard clause: Prevent deleting self
        if ($request->user()->id === $user->id) {
            return back()->withErrors(['current_password' => 'You cannot delete your own account from here.']);
        }

        // Guard clause: Prevent deleting the last remaining support user
        if ($user->role === RoleEnum::SUPPORT->value) {
            $supportCount = User::where('role', RoleEnum::SUPPORT->value)->count();
            if ($supportCount <= 1) {
                return back()->withErrors(['current_password' => 'Cannot delete the last support user.']);
            }
        }

        $user->delete();

        return redirect()->back()->with('success', 'User deleted successfully.');
    }
}