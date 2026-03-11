<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles Administrative management of Teams.
 */
class TeamController extends Controller
{
    /**
     * List all teams with their respective supporters.
     * * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $teams = Team::with('supporters')->get();
        return response()->json(['data' => $teams]);
    }

    /**
     * Create a new team with a specific shift.
     * * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'shift' => 'required|in:morning,afternoon,night',
        ]);

        $team = Team::create($validated);
        return response()->json(['data' => $team], 201);
    }

    /**
     * Update team details.
     * * @param Request $request
     * @param Team $team
     * @return JsonResponse
     */
    public function update(Request $request, Team $team): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'shift' => 'sometimes|in:morning,afternoon,night',
        ]);

        $team->update($validated);
        return response()->json(['data' => $team]);
    }

    /**
     * Delete a team record.
     * * @param Team $team
     * @return JsonResponse
     */
    public function destroy(Team $team): JsonResponse
    {
        $team->delete();
        return response()->json(null, 204);
    }
}