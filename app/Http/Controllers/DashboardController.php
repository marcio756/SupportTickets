<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Retrieves metrics and renders the application dashboard based on user role.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $metrics = [];

        // Separate metrics logic depending on the user's role 
        if ($user->isSupporter()) {
            $metrics = [
                'globalActiveTickets' => Ticket::whereNotIn('status', ['closed', 'resolved'])->count(),
                'globalResolvedTickets' => Ticket::where('status', 'resolved')->count(),
                'topClients' => User::where('role', 'customer')
                    ->withCount('tickets')
                    ->orderByDesc('tickets_count')
                    ->take(5)
                    ->get(['id', 'name', 'email', 'tickets_count']),
                'totalTimeSpentSeconds' => User::where('role', 'customer')->count() * 1800 - User::where('role', 'customer')->sum('daily_support_seconds')
            ];
        } else {
            $metrics = [
                'openTickets' => Ticket::where('customer_id', $user->id)->whereNotIn('status', ['closed', 'resolved'])->count(),
                'resolvedTickets' => Ticket::where('customer_id', $user->id)->whereIn('status', ['closed', 'resolved'])->count(),
                'remainingSeconds' => $user->daily_support_seconds,
                'totalAllowedSeconds' => 1800,
            ];
        }

        return Inertia::render('Dashboard', [
            'metrics' => $metrics,
        ]);
    }
}