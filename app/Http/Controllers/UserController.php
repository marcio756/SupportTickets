<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Traits\ChecksWorkSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class UserController extends Controller
{
    use ChecksWorkSession;

    /**
     * Display a listing of the resource.
     * Enforces hierarchy: Admins see all, Supporters see only customers.
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Only Admins and Supporters can access the user management panel
        if (!$user->isAdmin() && !$user->isSupporter()) {
            abort(403, 'Unauthorized access.');
        }

        $query = User::query();

        // Restrict supporters to only view and manage customers
        if ($user->isSupporter()) {
            $query->where('role', RoleEnum::CUSTOMER->value);
            $availableRoles = [RoleEnum::CUSTOMER->value];
        } else {
            // Admins can manage all roles
            $availableRoles = [RoleEnum::CUSTOMER->value, RoleEnum::SUPPORTER->value, RoleEnum::ADMIN->value];
        }

        $workSessionStatus = $this->getWorkSessionStatus($user);

        // If supporter is not actively clocked-in, block access to data
        if ($user->isSupporter() && $workSessionStatus !== \App\Enums\WorkSessionStatusEnum::ACTIVE->value) {
            return Inertia::render('Users/Index', [
                'users' => [
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 10,
                    'total' => 0,
                ],
                'filters' => $request->only(['query', 'role']),
                'roles' => $availableRoles,
                'workSessionStatus' => $workSessionStatus,
            ]);
        }

        if ($request->filled('query')) {
            $searchTerm = '%' . $request->input('query') . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  ->orWhere('email', 'like', $searchTerm);
            });
        }

        if ($request->filled('role') && in_array($request->input('role'), $availableRoles)) {
            $query->where('role', $request->input('role'));
        }

        $users = $query->paginate(10)->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['query', 'role']),
            'roles' => $availableRoles,
            'workSessionStatus' => $workSessionStatus,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $allowedRoles = $request->user()->isAdmin() 
            ? [RoleEnum::CUSTOMER->value, RoleEnum::SUPPORTER->value, RoleEnum::ADMIN->value]
            : [RoleEnum::CUSTOMER->value];

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')],
            'role' => ['required', Rule::in($allowedRoles)],
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
     * @param Request $request
     * @param User $targetUser
     * @return RedirectResponse
     */
    public function update(Request $request, User $targetUser): RedirectResponse
    {
        $actingUser = $request->user();

        // Prevent supporters from updating admins or other supporters
        if ($actingUser->isSupporter() && $targetUser->role !== RoleEnum::CUSTOMER) {
            abort(403, 'You can only edit customer accounts.');
        }

        $allowedRoles = $actingUser->isAdmin() 
            ? [RoleEnum::CUSTOMER->value, RoleEnum::SUPPORTER->value, RoleEnum::ADMIN->value]
            : [RoleEnum::CUSTOMER->value];

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($targetUser->id)],
            'role' => ['required', Rule::in($allowedRoles)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        // Prevent downgrading the last admin
        if ($targetUser->isAdmin() && $request->role !== RoleEnum::ADMIN->value) {
            $adminCount = User::where('role', RoleEnum::ADMIN->value)->count();
            if ($adminCount <= 1) {
                return back()->withErrors(['role' => 'Cannot change the role of the last administrator.']);
            }
        }

        $targetUser->update([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
        ]);

        if ($request->filled('password')) {
            $targetUser->update(['password' => Hash::make($request->password)]);
        }

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     * @param Request $request
     * @param User $user
     * @return RedirectResponse
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actingUser = $request->user();

        // Prevent supporters from deleting admins or other supporters
        if ($actingUser->isSupporter() && $user->role !== RoleEnum::CUSTOMER) {
            abort(403, 'You can only delete customer accounts.');
        }

        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        if ($actingUser->id === $user->id) {
            return back()->withErrors(['current_password' => 'You cannot delete your own account.']);
        }

        // Safeguard to prevent deletion of the last system administrator
        if ($user->isAdmin()) {
            $adminCount = User::where('role', RoleEnum::ADMIN->value)->count();
            if ($adminCount <= 1) {
                // Must return specifically on 'current_password' as expected by the inertia modal/test
                return back()->withErrors(['current_password' => 'Cannot delete the last administrator.']);
            }
        }

        $user->delete();

        return redirect()->back()->with('success', 'User deleted successfully.');
    }
}