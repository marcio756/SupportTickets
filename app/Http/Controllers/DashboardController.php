<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

/**
 * Handles the main web dashboard logic for all roles.
 * Architect Note: Highly optimized for scale. Heavy aggregates are resolved 
 * using targeted raw queries to prevent loading bloated Eloquent models into memory.
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
     * Aggregations optimized via DB::raw to avoid instantiating millions of Eloquent models.
     *
     * @return array
     */
    private function getAdminMetrics(): array
    {
        return Cache::remember('dashboard_admin_metrics', now()->addMinutes(30), function () {
            
            // Architect Note: Executing simple counts without instantiating models
            $globalActiveTickets = Ticket::whereNotIn('status', [TicketStatusEnum::CLOSED->value, TicketStatusEnum::RESOLVED->value])->count();
            $globalResolvedTickets = Ticket::where('status', TicketStatusEnum::RESOLVED->value)->count();
            $totalSupporters = User::where('role', RoleEnum::SUPPORTER->value)->count();

            // Architect Note: Replaced expensive withCount() on the entire users table 
            // with a direct grouping on the tickets table, joining users only for the top 5 results.
            $topClients = DB::table('tickets')
                ->select('users.id', 'users.name', 'users.email', DB::raw('COUNT(tickets.id) as tickets_count'))
                ->join('users', 'tickets.customer_id', '=', 'users.id')
                ->where('users.role', RoleEnum::CUSTOMER->value)
                ->groupBy('users.id', 'users.name', 'users.email')
                ->orderByDesc('tickets_count')
                ->limit(5)
                ->get();

            $topSupporters = DB::table('tickets')
                ->select('users.id', 'users.name', 'users.email', DB::raw('COUNT(tickets.id) as resolved_count'))
                ->join('users', 'tickets.assigned_to', '=', 'users.id')
                ->where('users.role', RoleEnum::SUPPORTER->value)
                ->where('tickets.status', TicketStatusEnum::RESOLVED->value)
                ->groupBy('users.id', 'users.name', 'users.email')
                ->orderByDesc('resolved_count')
                ->limit(5)
                ->get();

            return [
                'globalActiveTickets' => $globalActiveTickets,
                'globalResolvedTickets' => $globalResolvedTickets,
                'totalSupporters' => $totalSupporters,
                'topClients' => $topClients,
                'topSupporters' => $topSupporters,
            ];
        });
    }

    /**
     * Supporter metrics: Focused on personal productivity and daily shift.
     *
     * @param User $user
     * @return array
     */
    private function getSupporterMetrics(User $user): array
    {
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();
        
        /**
         * Architect Note: Replaced whereDate() with whereBetween() using explicit boundaries.
         * whereDate() forces the DB to run a DATE() function on every row (Full Table Scan).
         * whereBetween() allows the engine to jump directly to the index boundary instantly.
         */
        $totalWorkedSeconds = WorkSession::where('user_id', $user->id)
            ->whereBetween('started_at', [$todayStart, $todayEnd])
            ->sum('total_worked_seconds');

        $sharedMetrics = Cache::remember('dashboard_supporter_shared_metrics', now()->addMinutes(30), function () {
            return [
                'globalActiveTickets' => Ticket::whereNotIn('status', [TicketStatusEnum::CLOSED->value, TicketStatusEnum::RESOLVED->value])->count(),
                'globalResolvedTickets' => Ticket::where('status', TicketStatusEnum::RESOLVED->value)->count(),
                'topClients' => DB::table('tickets')
                    ->select('users.id', 'users.name', 'users.email', DB::raw('COUNT(tickets.id) as tickets_count'))
                    ->join('users', 'tickets.customer_id', '=', 'users.id')
                    ->where('users.role', RoleEnum::CUSTOMER->value)
                    ->groupBy('users.id', 'users.name', 'users.email')
                    ->orderByDesc('tickets_count')
                    ->limit(5)
                    ->get(),
            ];
        });

        return array_merge($sharedMetrics, [
            'totalTimeSpentSeconds' => (int) $totalWorkedSeconds,
        ]);
    }

    /**
     * Customer metrics: Personal ticket status and remaining credits.
     *
     * @param User $user
     * @return array
     */
    private function getCustomerMetrics(User $user): array
    {
        return Cache::remember("dashboard_customer_metrics_{$user->id}", now()->addMinutes(5), function () use ($user) {
            return [
                'openTickets' => Ticket::where('customer_id', $user->id)
                    ->whereNotIn('status', [TicketStatusEnum::CLOSED->value, TicketStatusEnum::RESOLVED->value])->count(),
                'resolvedTickets' => Ticket::where('customer_id', $user->id)
                    ->whereIn('status', [TicketStatusEnum::CLOSED->value, TicketStatusEnum::RESOLVED->value])->count(),
                'remainingSeconds' => $user->daily_support_seconds,
            ];
        });
    }
}