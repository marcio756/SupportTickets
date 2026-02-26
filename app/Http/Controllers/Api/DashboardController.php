<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Provides metrics and statistics for the mobile application dashboard
 */
class DashboardController extends Controller
{
    /**
     * Retrieve dashboard statistics based on user role
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $metrics = [];

        if ($user->isSupporter()) {
            // Fetch top 5 customers with the highest number of tickets
            // Note: This assumes you have a 'tickets' relationship defined in your User model.
            $topClients = User::where('role', 'customer')
                ->withCount('tickets')
                ->orderByDesc('tickets_count')
                ->take(5)
                ->get(['id', 'name', 'email']);

            // Fetch actual Time Spent Today. 
            // Assuming time tracked in seconds across tickets, converted to hours.
            // If you don't have this tracked yet in the DB, replace with your actual query or keep as a placeholder.
            $timeSpentToday = 0.0; 
            
            $metrics = [
                'active_tickets' => Ticket::whereNotIn('status', ['closed', 'resolved'])->count(),
                'resolved_tickets' => Ticket::where('status', 'resolved')->count(),
                'time_spent_today' => $timeSpentToday,
                'top_clients' => $topClients,
            ];
        } else {
            $metrics = [
                'open_tickets' => Ticket::where('customer_id', $user->id)
                    ->whereNotIn('status', ['closed', 'resolved'])->count(),
                'resolved_tickets' => Ticket::where('customer_id', $user->id)
                    ->whereIn('status', ['closed', 'resolved'])->count(),
                'remaining_seconds' => $user->daily_support_seconds,
                'total_daily_limit' => 1800,
            ];
        }

        return response()->json($metrics);
    }
}