<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    use ApiResponser;

    /**
     * Tempo de vida do Cache para o Dashboard (5 minutos).
     * @var int
     */
    private const CACHE_TTL = 300;

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
        // Arquiteto: O Cache::remember evita que a Base de Dados faça "Table Scans" de milhões de registos
        // a cada vez que o Admin entra na página inicial.
        $data = Cache::remember('dashboard:admin', self::CACHE_TTL, function () {
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

            return [
                'role' => 'admin',
                'stats' => [
                    'open_tickets' => $openTicketsCount,
                    'resolved_tickets' => $resolvedTicketsCount,
                    'total_customers' => $totalCustomers,
                ],
                'top_customers' => $topCustomers,
                'top_supporters' => $topSupporters,
            ];
        });

        return $this->successResponse($data, 'Admin dashboard retrieved successfully.');
    }

    /**
     * Formats the specific dashboard statistics for Supporters.
     */
    private function getSupporterDashboard(User $user): JsonResponse
    {
        $data = Cache::remember("dashboard:supporter:{$user->id}", self::CACHE_TTL, function () use ($user) {
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

            return [
                'role' => 'supporter',
                'stats' => [
                    'open_tickets' => $openTicketsCount,
                    'my_tickets' => $myTicketsCount,
                    'resolved_today' => $resolvedToday,
                ],
                'top_customers' => $topCustomers,
            ];
        });

        return $this->successResponse($data, 'Supporter dashboard retrieved successfully.');
    }

    /**
     * Formats the specific dashboard statistics for Customers.
     */
    private function getCustomerDashboard(User $user): JsonResponse
    {
        $data = Cache::remember("dashboard:customer:{$user->id}", self::CACHE_TTL, function () use ($user) {
            $myOpenTickets = Ticket::where('customer_id', $user->id)->whereNotIn('status', ['closed', 'resolved'])->count();
            $myTotalTickets = Ticket::where('customer_id', $user->id)->count();

            return [
                'role' => 'customer',
                'stats' => [
                    'my_open_tickets' => $myOpenTickets,
                    'my_total_tickets' => $myTotalTickets,
                ]
            ];
        });

        return $this->successResponse($data, 'Customer dashboard retrieved successfully.');
    }
}