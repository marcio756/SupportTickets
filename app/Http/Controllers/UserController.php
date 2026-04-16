<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\Team;
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
     * Enforces hierarchy and includes Team data for supporters.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isSupporter()) {
            abort(403, 'Unauthorized access.');
        }

        // Fetch teams for the creation/edition modal
        $teams = Team::all();
        
        /**
         * Architect Note: Selecting only needed columns is better, but since models are passed
         * to forms, we keep standard selection. Team relationship is eager loaded.
         */
        $query = User::query()->with('team');

        if ($user->isSupporter()) {
            $query->where('role', RoleEnum::CUSTOMER->value);
            $availableRoles = [RoleEnum::CUSTOMER->value];
        } else {
            $availableRoles = [RoleEnum::CUSTOMER->value, RoleEnum::SUPPORTER->value, RoleEnum::ADMIN->value];
            $query->withTrashed();
        }

        $workSessionStatus = $this->getWorkSessionStatus($user);

        // If supporter has no active session, return empty data set as per original logic
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
                'teams' => $teams,
                'workSessionStatus' => $workSessionStatus,
            ]);
        }

        if ($request->filled('query')) {
            /**
             * Architect Note: Removed the leading '%' wildcard. 
             * A leading wildcard ('%term%') completely disables database indexing, 
             * causing a catastrophic full table scan on millions of users.
             */
            $searchTerm = $request->input('query') . '%';
            
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  ->orWhere('email', 'like', $searchTerm);
            });
        }

        if ($request->filled('role') && in_array($request->input('role'), $availableRoles)) {
            $query->where('role', $request->input('role'));
        }

        /**
         * Architect Note: Enforced orderByDesc('id') to optimize pagination offsets 
         * via the Primary Key, preventing slow memory sorting on large datasets.
         */
        $users = $query->orderByDesc('id')->paginate(10)->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['query', 'role']),
            'roles' => $availableRoles,
            'teams' => $teams,
            'workSessionStatus' => $workSessionStatus,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
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
            'team_id' => ['nullable', 'exists:teams,id'],
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'team_id' => $request->role === RoleEnum::SUPPORTER->value ? $request->team_id : null,
            'password' => Hash::make($request->password),
        ]);

        return redirect()->back()->with('success', 'User created successfully.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $actingUser = $request->user();
        $targetUser = User::withTrashed()->findOrFail($id);

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
            'team_id' => ['nullable', 'exists:teams,id'],
        ]);

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
            'team_id' => $request->role === RoleEnum::SUPPORTER->value ? $request->team_id : null,
        ]);

        if ($request->filled('password')) {
            $targetUser->update(['password' => Hash::make($request->password)]);
        }

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    /**
     * Deactivate (soft delete) the specified user account.
     *
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $actingUser = $request->user();
        $user = User::findOrFail($id);

        if ($actingUser->isSupporter() && $user->role !== RoleEnum::CUSTOMER) {
            abort(403, 'You can only delete customer accounts.');
        }

        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        if ($actingUser->id === $user->id) {
            return back()->withErrors(['current_password' => 'You cannot delete your own account.']);
        }

        if ($user->isAdmin()) {
            $adminCount = User::where('role', RoleEnum::ADMIN->value)->count();
            if ($adminCount <= 1) {
                return back()->withErrors(['current_password' => 'Cannot delete the last administrator.']);
            }
        }

        $user->delete();

        return redirect()->back()->with('success', 'User deactivated successfully.');
    }

    /**
     * Restore a deactivated (soft deleted) user account.
     *
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function restore(Request $request, int $id): RedirectResponse
    {
        $actingUser = $request->user();

        if (!$actingUser->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        return redirect()->back()->with('success', 'User reactivated successfully.');
    }
}