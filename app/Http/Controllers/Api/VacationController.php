<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateVacationStatusRequest;
use App\Models\User;
use App\Models\Vacation;
use App\Services\VacationCalendarService;
use App\Services\VacationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Handles the API endpoints for vacation booking and retrieval.
 * Architect Note: Refactored pagination to simplePaginate to prevent O(N) COUNT(*) 
 * database bottlenecks when the historical data grows to thousands of records.
 */
class VacationController extends Controller
{
    private VacationService $vacationService;
    private VacationCalendarService $calendarService;

    public function __construct(VacationService $vacationService, VacationCalendarService $calendarService)
    {
        $this->vacationService = $vacationService;
        $this->calendarService = $calendarService;
    }

    /**
     * Get a global list of vacations. Supports filtering for specific date ranges or teams.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Vacation::with(['supporter.team']);

        if ($request->has('team_id')) {
            $query->whereHas('supporter', function ($q) use ($request) {
                $q->where('team_id', $request->team_id);
            });
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('start_date', [$request->start_date, $request->end_date]);
        }

        // Optimization: simplePaginate uses just "limit/offset" without running a full table COUNT(*)
        $vacations = $query->orderByDesc('id')->simplePaginate(20);

        return response()->json(['data' => $vacations]);
    }

    /**
     * Exposes structured calendar data for UI rendering.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function calendar(Request $request): JsonResponse
    {
        $year = $request->get('year', Carbon::now()->year);
        $calendarData = $this->calendarService->getCalendarData($year);
        
        return response()->json(['data' => $calendarData]);
    }

    /**
     * Store a new vacation request for the authenticated supporter.
     *
     * @param Request $request
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
     * Update an existing pending vacation request for the authenticated supporter.
     *
     * @param Request $request
     * @param Vacation $vacation
     * @return JsonResponse
     */
    public function update(Request $request, Vacation $vacation): JsonResponse
    {
        if ($vacation->supporter_id !== Auth::id()) {
            return response()->json(['message' => 'Não autorizado a editar este pedido.'], 403);
        }

        if ($vacation->status !== 'pending') {
            return response()->json(['message' => 'Apenas é possível editar pedidos de férias pendentes.'], 422);
        }

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $vacation->update([
            'start_date' => Carbon::parse($validated['start_date']),
            'end_date' => Carbon::parse($validated['end_date']),
            'total_days' => Carbon::parse($validated['start_date'])->diffInDays(Carbon::parse($validated['end_date'])) + 1,
        ]);

        return response()->json(['data' => $vacation]);
    }

    /**
     * Update the approval status of a vacation.
     *
     * @param UpdateVacationStatusRequest $request
     * @param Vacation $vacation
     * @return JsonResponse
     */
    public function updateStatus(UpdateVacationStatusRequest $request, Vacation $vacation): JsonResponse
    {
        $vacation = $this->vacationService->updateStatus($vacation, $request->validated('status'));
        return response()->json(['data' => $vacation]);
    }

    /**
     * Retrieve vacation history and summary for a specific supporter.
     *
     * @param User $supporter
     * @return JsonResponse
     */
    public function showBySupporter(User $supporter): JsonResponse
    {
        $vacations = Vacation::where('supporter_id', $supporter->id)->get();
        
        $currentYear = Carbon::now()->year;
        $usedDays = $vacations->where('year', $currentYear)->where('status', '!=', 'rejected')->sum('total_days');
        
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
     * Remove or cancel a vacation record.
     *
     * @param Vacation $vacation
     * @return JsonResponse
     */
    public function destroy(Vacation $vacation): JsonResponse
    {
        $vacation->delete();
        return response()->json(null, 204);
    }
}