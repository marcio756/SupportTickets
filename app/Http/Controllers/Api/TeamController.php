<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignTeamMembersRequest;
use App\Models\Team;
use App\Models\User;
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
     * Returns the list of members assigned to a specific team.
     * * @param Team $team
     * @return JsonResponse
     */
    public function members(Team $team): JsonResponse
    {
        // Explicitly return the supporters relationship
        return response()->json(['data' => $team->supporters]);
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

    /**
     * Bulk assigns multiple users to a specific team.
     * * @param AssignTeamMembersRequest $request
     * @param Team $team
     * @return JsonResponse
     */
    public function assignMembers(AssignTeamMembersRequest $request, Team $team): JsonResponse
    {
        User::whereIn('id', $request->validated('user_ids'))
            ->update(['team_id' => $team->id]);

        return response()->json([
            'message' => 'Members assigned successfully',
            'data' => $team->load('supporters')
        ]);
    }
}