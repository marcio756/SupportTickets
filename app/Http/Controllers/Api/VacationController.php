<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vacation;
use App\Services\VacationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Handles the API endpoints for vacation booking and retrieval.
 */
class VacationController extends Controller
{
    /** @var VacationService */
    private VacationService $vacationService;

    /**
     * Injecting the business logic service.
     * * @param VacationService $vacationService
     */
    public function __construct(VacationService $vacationService)
    {
        $this->vacationService = $vacationService;
    }

    /**
     * Get a global list of vacations for the calendar view.
     * * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $vacations = Vacation::with(['supporter.team'])->get();
        return response()->json(['data' => $vacations]);
    }

    /**
     * Store a new vacation request for the authenticated supporter.
     * * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        /** @var User $supporter */
        $supporter = Auth::user();

        $vacation = $this->vacationService->bookVacation(
            $supporter,
            Carbon::parse($validated['start_date']),
            Carbon::parse($validated['end_date'])
        );

        return response()->json(['data' => $vacation], 201);
    }

    /**
     * Retrieve vacation history and summary for a specific supporter.
     * * @param User $supporter
     * @return JsonResponse
     */
    public function showBySupporter(User $supporter): JsonResponse
    {
        $vacations = Vacation::where('supporter_id', $supporter->id)->get();
        
        $currentYear = Carbon::now()->year;
        $usedDays = $vacations->where('year', $currentYear)->sum('total_days');
        
        return response()->json([
            'data' => $vacations,
            'summary' => [
                'total_allowed' => 22,
                'used_days' => $usedDays,
                'remaining_days' => max(0, 22 - $usedDays),
                'year' => $currentYear,
            ]
        ]);
    }

    /**
     * Remove a vacation record.
     * * @param Vacation $vacation
     * @return JsonResponse
     */
    public function destroy(Vacation $vacation): JsonResponse
    {
        // Ideally, authorize here: only the owner or an admin should delete.
        $vacation->delete();
        return response()->json(null, 204);
    }
}