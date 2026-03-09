<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponser;

    /**
     * Retrieve dashboard statistics based on user role.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $this->getAdminDashboard();
        }

        if ($user->isSupporter()) {
            return $this->getSupporterDashboard($user);
        }

        return $this->getCustomerDashboard($user);
    }

    /**
     * Formats the specific dashboard statistics for Administrators.
     */
    private function getAdminDashboard(): JsonResponse
    {
        // Estatísticas Gerais (Cards)
        $openTicketsCount = Ticket::whereNotIn('status', ['closed', 'resolved'])->count();
        $resolvedTicketsCount = Ticket::whereIn('status', ['closed', 'resolved'])->count();
        $totalCustomers = User::where('role', 'customer')->count();

        // Tabela: Top 5 Customers com mais tickets (criados por eles)
        $topCustomers = User::where('role', 'customer')
            ->withCount('tickets')
            ->orderByDesc('tickets_count')
            ->limit(5)
            ->get(['id', 'name', 'email', 'tickets_count']);

        // Tabela: Top 5 Supporters com mais tickets resolvidos (atribuídos a eles e fechados)
        $topSupporters = User::where('role', 'supporter')
            ->withCount(['assignedTickets as resolved_count' => function ($query) {
                $query->whereIn('status', ['closed', 'resolved']);
            }])
            ->orderByDesc('resolved_count')
            ->limit(5)
            ->get(['id', 'name', 'email', 'resolved_count']);

        return $this->successResponse([
            'role' => 'admin',
            'stats' => [
                'open_tickets' => $openTicketsCount,
                'resolved_tickets' => $resolvedTicketsCount,
                'total_customers' => $totalCustomers,
            ],
            'top_customers' => $topCustomers,
            'top_supporters' => $topSupporters,
        ], 'Admin dashboard retrieved successfully.');
    }

    /**
     * Formats the specific dashboard statistics for Supporters.
     */
    private function getSupporterDashboard(User $user): JsonResponse
    {
        $openTicketsCount = Ticket::whereNotIn('status', ['closed', 'resolved'])->count();
        $myTicketsCount = Ticket::where('assigned_to', $user->id)->whereNotIn('status', ['closed', 'resolved'])->count();
        $resolvedToday = Ticket::where('assigned_to', $user->id)
            ->whereIn('status', ['closed', 'resolved'])
            ->whereDate('updated_at', today())
            ->count();

        // Tabela: Top 5 Customers agora também disponível para os Supporters
        $topCustomers = User::where('role', 'customer')
            ->withCount('tickets')
            ->orderByDesc('tickets_count')
            ->limit(5)
            ->get(['id', 'name', 'email', 'tickets_count']);

        return $this->successResponse([
            'role' => 'supporter',
            'stats' => [
                'open_tickets' => $openTicketsCount,
                'my_tickets' => $myTicketsCount,
                'resolved_today' => $resolvedToday,
            ],
            'top_customers' => $topCustomers,
        ], 'Supporter dashboard retrieved successfully.');
    }

    /**
     * Formats the specific dashboard statistics for Customers.
     */
    private function getCustomerDashboard(User $user): JsonResponse
    {
        $myOpenTickets = Ticket::where('customer_id', $user->id)->whereNotIn('status', ['closed', 'resolved'])->count();
        $myTotalTickets = Ticket::where('customer_id', $user->id)->count();

        return $this->successResponse([
            'role' => 'customer',
            'stats' => [
                'my_open_tickets' => $myOpenTickets,
                'my_total_tickets' => $myTotalTickets,
            ]
        ], 'Customer dashboard retrieved successfully.');
    }
}