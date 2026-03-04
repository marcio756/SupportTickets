<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Enums\TicketStatusEnum;
use App\Enums\WorkSessionStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Retrieves metrics and renders the application dashboard based on user role.
     * Integrates distinct metric compilation for Admins, Supporters, and Customers.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $metrics = [];

        // Build a specialized comprehensive overview for system administrators
        if ($user->isAdmin()) {
            $metrics = [
                'globalActiveTickets' => Ticket::whereNotIn('status', [TicketStatusEnum::CLOSED->value, TicketStatusEnum::RESOLVED->value])->count(),
                'supportsActive' => WorkSession::where('status', WorkSessionStatusEnum::ACTIVE->value)->distinct('user_id')->count(),
                'globalResolvedTickets' => Ticket::where('status', TicketStatusEnum::RESOLVED->value)->count(),
                'topClients' => User::where('role', RoleEnum::CUSTOMER->value)
                    ->withCount('tickets')
                    ->orderByDesc('tickets_count')
                    ->take(5)
                    ->get(['id', 'name', 'email', 'tickets_count']),
                'topSupporters' => User::where('role', RoleEnum::SUPPORTER->value)
                    ->withCount(['assignedTickets' => function ($query) {
                        $query->where('status', TicketStatusEnum::RESOLVED->value);
                    }])
                    ->orderByDesc('assigned_tickets_count')
                    ->take(5)
                    ->get(['id', 'name', 'email', 'assigned_tickets_count']),
            ];
        } 
        // Build the operational view for internal support staff
        elseif ($user->isSupporter()) {
            $metrics = [
                'globalActiveTickets' => Ticket::whereNotIn('status', [TicketStatusEnum::CLOSED->value, TicketStatusEnum::RESOLVED->value])->count(),
                'globalResolvedTickets' => Ticket::where('status', TicketStatusEnum::RESOLVED->value)->count(),
                'topClients' => User::where('role', RoleEnum::CUSTOMER->value)
                    ->withCount('tickets')
                    ->orderByDesc('tickets_count')
                    ->take(5)
                    ->get(['id', 'name', 'email', 'tickets_count']),
                'totalTimeSpentSeconds' => User::where('role', RoleEnum::CUSTOMER->value)->count() * 1800 - User::where('role', RoleEnum::CUSTOMER->value)->sum('daily_support_seconds')
            ];
        } 
        // Build the restricted self-service view for customers
        else {
            $metrics = [
                'openTickets' => Ticket::where('customer_id', $user->id)->whereNotIn('status', [TicketStatusEnum::CLOSED->value, TicketStatusEnum::RESOLVED->value])->count(),
                'resolvedTickets' => Ticket::where('customer_id', $user->id)->whereIn('status', [TicketStatusEnum::CLOSED->value, TicketStatusEnum::RESOLVED->value])->count(),
                'remainingSeconds' => $user->daily_support_seconds,
                'totalAllowedSeconds' => 1800,
            ];
        }

        return Inertia::render('Dashboard', [
            'metrics' => $metrics,
        ]);
    }
}