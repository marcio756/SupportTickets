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

        // 1. Carregar apenas a lista estática de Supporters
        $supporters = User::where('role', RoleEnum::SUPPORTER->value)
            ->with('team:id,name')
            ->get(['id', 'name', 'team_id']);
        
        // 2. Cálculos de KPI otimizados (Agregação direta na DB sem carregar os registos)
        $summaryQuery = Vacation::where('year', $currentYear)->where('status', '!=', 'rejected');
        if (!$user->isAdmin()) {
            $summaryQuery->where('supporter_id', $user->id);
        }
        $usedDays = (int) $summaryQuery->sum('total_days');

        if ($user->isAdmin()) {
            $globalAllowed = $supporters->count() * 22;
            $summary = [
                'total_allowed' => $globalAllowed,
                'used_days' => $usedDays,
                'remaining_days' => max(0, $globalAllowed - $usedDays),
                'year' => $currentYear,
            ];
        } else {
            $summary = [
                'total_allowed' => 22,
                'used_days' => $usedDays,
                'remaining_days' => max(0, 22 - $usedDays),
                'year' => $currentYear,
            ];
        }

        // 3. Paginação Server-Side EXCLUSIVA para a tabela (Apenas os Admins a veem)
        $vacations = null;
        if ($user->isAdmin()) {
            $query = Vacation::with('supporter.team:id,name')
                ->select(['id', 'supporter_id', 'start_date', 'end_date', 'total_days', 'status', 'year']);
            
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('supporter_id')) {
                $query->where('supporter_id', $request->supporter_id);
            }
            if ($request->filled('date_from')) {
                $query->where('end_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->where('start_date', '<=', $request->date_to);
            }

            // A BD devolve apenas os 10 registos solicitados e a meta-informação das páginas
            $vacations = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();
        }

        // 4. Extração contida para o Calendário (Restringimos sempre ao Ano para não explodir a memória)
        $calendarQuery = Vacation::with('supporter.team:id,name')
            ->select(['id', 'supporter_id', 'start_date', 'end_date', 'status', 'year'])
            ->where('status', '!=', 'rejected');
            
        if ($request->filled('supporter_id')) {
            $calendarQuery->where('supporter_id', $request->supporter_id);
        }
        
        $yearToFetch = $request->filled('date_from') ? Carbon::parse($request->date_from)->year : $currentYear;
        $calendarQuery->where('year', $yearToFetch);

        $calendarVacations = $calendarQuery->get();

        return Inertia::render('Vacations/Index', [
            'supporters' => $supporters,
            'vacations' => $vacations, 
            'calendarVacations' => $calendarVacations,
            'summary' => $summary,
            'filters' => $request->only(['status', 'supporter_id', 'date_from', 'date_to'])
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