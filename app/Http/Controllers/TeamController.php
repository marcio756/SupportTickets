<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class TeamController extends Controller
{
    /**
     * Architectural Note:
     * Switched from `get()` to `paginate()` to prevent out-of-memory errors on large datasets.
     * Supporters collection is initialized empty to delegate population to asynchronous frontend queries.
     */
    public function index(Request $request): Response
    {
        $teams = Team::with(['supporters' => function($query) {
            $query->select('id', 'name', 'team_id');
        }])->paginate(15)->withQueryString();
        
        // Return empty array to enforce async loading on the UI layer.
        $supporters = [];
        
        return Inertia::render('Teams/Index', [
            'teams' => $teams,
            'supporters' => $supporters
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'shift' => 'required|in:morning,afternoon,night',
            'supporter_ids' => 'nullable|array',
            'supporter_ids.*' => 'exists:users,id',
        ]);

        $team = Team::create([
            'name' => $validated['name'],
            'shift' => $validated['shift'],
        ]);

        if (!empty($validated['supporter_ids'])) {
            User::whereIn('id', $validated['supporter_ids'])->update(['team_id' => $team->id]);
        }

        return redirect()->back()->with('success', 'Team created successfully.');
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'shift' => 'required|in:morning,afternoon,night',
            'supporter_ids' => 'nullable|array',
            'supporter_ids.*' => 'exists:users,id',
        ]);

        $team->update([
            'name' => $validated['name'],
            'shift' => $validated['shift'],
        ]);

        // Remove the team_id from users that are no longer selected
        User::where('team_id', $team->id)
            ->whereNotIn('id', $validated['supporter_ids'] ?? [])
            ->update(['team_id' => null]);

        // Add the team_id to the selected users
        if (!empty($validated['supporter_ids'])) {
            User::whereIn('id', $validated['supporter_ids'])->update(['team_id' => $team->id]);
        }

        return redirect()->back()->with('success', 'Team updated successfully.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $team->delete();
        return redirect()->back()->with('success', 'Team deleted successfully.');
    }
}