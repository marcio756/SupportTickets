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
    // ... index and store methods remain the same ...

    public function update(Request $request, User $user)
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

    public function destroy(Request $request, User $user)
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