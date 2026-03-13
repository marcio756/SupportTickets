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

class VacationController extends Controller
{
    private VacationService $vacationService;

    public function __construct(VacationService $vacationService)
    {
        $this->vacationService = $vacationService;
    }

    public function index(Request $request): Response
    {
        // ---------------------------------------------------------
        // TRUQUE INTELIGENTE: Atualização Automática de Estado
        // Transforma todas as férias aprovadas que já passaram em 'completed'
        // ---------------------------------------------------------
        Vacation::where('status', 'approved')
            ->where('end_date', '<', Carbon::today()->toDateString())
            ->update(['status' => 'completed']);

        $user = $request->user();
        $currentYear = Carbon::now()->year;

        $supporters = User::where('role', RoleEnum::SUPPORTER->value)->with('team')->get();
        $globalVacations = Vacation::with('supporter.team')->orderBy('created_at', 'desc')->get();
        
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
            'status' => 'required|in:pending,approved,rejected,completed', // Adicionado completed
        ]);

        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);

        if ($start->year !== $end->year) {
            return redirect()->back()->withErrors(['start_date' => 'Dates must be within the same year.']);
        }

        $workingDays = 0;
        foreach ($start->daysUntil($end) as $date) {
            if ($date->isWeekday()) {
                $workingDays++;
            }
        }

        if ($workingDays === 0) {
            return redirect()->back()->withErrors(['start_date' => 'The selected period contains no working days.']);
        }

        $vacation->update([
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'total_days' => $workingDays,
            'status' => $validated['status'],
        ]);

        return redirect()->back()->with('success', 'Vacation updated successfully.');
    }

    public function updateStatus(Request $request, Vacation $vacation): RedirectResponse
    {
        if (!$request->user()->isAdmin()) abort(403);
        // Admin quick actions
        $validated = $request->validate(['status' => 'required|in:approved,rejected']);
        $vacation->update(['status' => $validated['status']]);
        return redirect()->back()->with('success', "Vacation marked as {$validated['status']}.");
    }

    public function destroy(Vacation $vacation): RedirectResponse
    {
        $vacation->delete();
        return redirect()->back()->with('success', 'Vacation removed.');
    }
}