<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Handles the discovery of system resources (like users filtered by specific roles) for UI selection components.
 * This extraction from route closures allows Laravel to successfully cache the application routes, 
 * improving overall framework boot times and adhering strictly to the Single Responsibility Principle.
 */
class DiscoveryController extends Controller
{
    /**
     * Retrieves a paginated list of customers tailored for UI dropdowns or autocompletes.
     * We use cursor pagination here to guarantee stable query execution times and low memory usage 
     * even when the customer table scales to millions of records.
     *
     * @return JsonResponse
     */
    public function customers(): JsonResponse
    {
        $customers = User::where('role', RoleEnum::CUSTOMER->value)
            ->select('id', 'name', 'email')
            ->cursorPaginate(50);

        return response()->json($customers);
    }

    /**
     * Retrieves a paginated list of support agents along with their team context.
     * The eager loading of the 'team' relationship is maintained to prevent N+1 query problems
     * on the frontend when rendering agent details.
     *
     * @return JsonResponse
     */
    public function supporters(): JsonResponse
    {
        $supporters = User::where('role', RoleEnum::SUPPORTER->value)
            ->select('id', 'name', 'email', 'team_id')
            ->with('team')
            ->cursorPaginate(50);

        return response()->json($supporters);
    }
}