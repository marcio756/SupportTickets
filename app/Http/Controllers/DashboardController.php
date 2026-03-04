<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

/**
 * Handles the main web dashboard logic for all roles.
 */
class DashboardController extends Controller
{
    /**
     * Renders the dashboard with role-specific metrics.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $metrics = $user->isAdmin() 
            ? $this->getAdminMetrics() 
            : ($user->isSupporter() ? $this->getSupporterMetrics($user) : $this->getCustomerMetrics($user));

        return Inertia::render('Dashboard', [
            'metrics' => $metrics,
        ]);
    }

    /**
     * Admin metrics: Focused on global oversight and team performance.
     * @return array
     */
    private function getAdminMetrics(): array
    {
        return [
            'globalActiveTickets' => Ticket::whereNotIn('status', [TicketStatusEnum::CLOSED, TicketStatusEnum::RESOLVED])->count(),
            'globalResolvedTickets' => Ticket::where('status', TicketStatusEnum::RESOLVED)->count(),
            'totalSupporters' => User::where('role', RoleEnum::SUPPORTER)->count(),
            'topClients' => User::where('role', RoleEnum::CUSTOMER)
                ->withCount('tickets')
                ->orderByDesc('tickets_count')
                ->take(5)
                ->get(['id', 'name', 'email']),
            'topSupporters' => User::where('role', RoleEnum::SUPPORTER)
                ->withCount(['assignedTickets as resolved_count' => function ($query) {
                    $query->where('status', TicketStatusEnum::RESOLVED);
                }])
                ->orderByDesc('resolved_count')
                ->take(5)
                ->get(['id', 'name', 'email', 'resolved_count']),
        ];
    }

    /**
     * Supporter metrics: Focused on personal productivity and daily shift.
     * @param User $user
     * @return array
     */
    private function getSupporterMetrics(User $user): array
    {
        $today = Carbon::today();
        
        $totalWorkedSeconds = WorkSession::where('user_id', $user->id)
            ->whereDate('started_at', $today)
            ->sum('total_worked_seconds');

        return [
            'globalActiveTickets' => Ticket::whereNotIn('status', [TicketStatusEnum::CLOSED, TicketStatusEnum::RESOLVED])->count(),
            'globalResolvedTickets' => Ticket::where('status', TicketStatusEnum::RESOLVED)->count(),
            'totalTimeSpentSeconds' => (int) $totalWorkedSeconds,
            'topClients' => User::where('role', RoleEnum::CUSTOMER)
                ->withCount('tickets')
                ->orderByDesc('tickets_count')
                ->take(5)
                ->get(['id', 'name', 'email']),
        ];
    }

    /**
     * Customer metrics: Personal ticket status and remaining credits.
     * @param User $user
     * @return array
     */
    private function getCustomerMetrics(User $user): array
    {
        return [
            'openTickets' => Ticket::where('customer_id', $user->id)
                ->whereNotIn('status', [TicketStatusEnum::CLOSED, TicketStatusEnum::RESOLVED])->count(),
            'resolvedTickets' => Ticket::where('customer_id', $user->id)
                ->whereIn('status', [TicketStatusEnum::CLOSED, TicketStatusEnum::RESOLVED])->count(),
            'remainingSeconds' => $user->daily_support_seconds,
        ];
    }
}