<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Models\Vacation;
use App\Services\VacationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;

/**
 * Handles vacation requests and administrative overviews.
 */
class VacationController extends Controller
{
    private VacationService $vacationService;

    public function __construct(VacationService $vacationService)
    {
        $this->vacationService = $vacationService;
    }

    /**
     * Renders the vacations dashboard.
     * * Architectural Note: 
     * The legacy update query was removed from this GET request to respect SRP and prevent DB locks.
     * Use a scheduled Laravel Command (Cron) to transition approved vacations to completed.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $currentYear = Carbon::now()->year;

        /**
         * Architectural Note: 
         * Strict column selection prevents loading massive user objects into RAM.
         */
        $supporters = User::where('role', RoleEnum::SUPPORTER->value)
            ->with('team:id,name')
            ->get(['id', 'name', 'team_id']);
        
        $globalVacations = Vacation::with('supporter.team:id,name')
            ->select(['id', 'supporter_id', 'start_date', 'end_date', 'total_days', 'status', 'year'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        if ($user->isAdmin()) {
            $totalSupporters = $supporters->count();
            $globalAllowed = $totalSupporters * 22;
            $globalUsed = $globalVacations->where('year', $currentYear)->where('status', '!=', 'rejected')->sum('total_days');

            $summary = [
                'total_allowed' => $globalAllowed,
                'used_days' => $globalUsed,
                'remaining_days' => max(0, $globalAllowed - $globalUsed),
                'year' => $currentYear,
            ];
        } else {
            $myVacations = $globalVacations->where('supporter_id', $user->id);
            $myUsedDays = $myVacations->where('year', $currentYear)->where('status', '!=', 'rejected')->sum('total_days');

            $summary = [
                'total_allowed' => 22,
                'used_days' => $myUsedDays,
                'remaining_days' => max(0, 22 - $myUsedDays),
                'year' => $currentYear,
            ];
        }

        return Inertia::render('Vacations/Index', [
            'supporters' => $supporters,
            'vacations' => $globalVacations,
            'summary' => $summary
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->isAdmin()) abort(403, 'Admins cannot book vacations in this system.');

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            $this->vacationService->bookVacation(
                $request->user(),
                Carbon::parse($validated['start_date']),
                Carbon::parse($validated['end_date'])
            );
            return redirect()->back()->with('success', 'Vacation booked successfully. Awaiting admin approval.');
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }
    }

    public function update(Request $request, Vacation $vacation): RedirectResponse
    {
        if (!$request->user()->isAdmin()) abort(403);

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:pending,approved,rejected,completed',
        ]);

        try {
            $this->vacationService->updateVacation(
                $vacation,
                Carbon::parse($validated['start_date']),
                Carbon::parse($validated['end_date']),
                $validated['status']
            );
            return redirect()->back()->with('success', 'Vacation updated successfully.');
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }
    }

    public function updateStatus(Request $request, Vacation $vacation): RedirectResponse
    {
        if (!$request->user()->isAdmin()) abort(403);
        
        $validated = $request->validate(['status' => 'required|in:approved,rejected']);
        
        try {
            $this->vacationService->updateStatus($vacation, $validated['status']);
            return redirect()->back()->with('success', "Vacation marked as {$validated['status']}.");
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }
    }

    public function destroy(Vacation $vacation): RedirectResponse
    {
        $vacation->delete();
        return redirect()->back()->with('success', 'Vacation removed.');
    }
}