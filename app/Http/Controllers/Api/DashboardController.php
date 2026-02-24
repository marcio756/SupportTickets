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
            $metrics = [
                'active_tickets' => Ticket::whereNotIn('status', ['closed', 'resolved'])->count(),
                'resolved_tickets' => Ticket::where('status', 'resolved')->count(),
                'total_customers' => User::where('role', 'customer')->count(),
            ];
        } else {
            $metrics = [
                'open_tickets' => Ticket::where('customer_id', $user->id)
                    ->whereNotIn('status', ['closed', 'resolved'])->count(),
                'resolved_tickets' => Ticket::where('customer_id', $user->id)
                    ->whereIn('status', ['closed', 'resolved'])->count(),
                'remaining_seconds' => $user->daily_support_seconds, // Field from User model
                'total_daily_limit' => 1800, // Standard limit as defined in DashboardController
            ];
        }

        return response()->json($metrics);
    }
}