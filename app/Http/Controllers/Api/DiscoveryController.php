<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Handles the discovery of system resources (like users filtered by specific roles) for UI selection components.
 */
class DiscoveryController extends Controller
{
    /**
     * Retrieves a cursor-paginated list of customers tailored for UI dropdowns or autocompletes.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function customers(Request $request): JsonResponse
    {
        try {
            $query = User::where('role', RoleEnum::CUSTOMER->value)
                ->select('id', 'name', 'email');

            if ($request->filled('search')) {
                $searchTerm = '%' . $request->input('search') . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'like', $searchTerm)
                      ->orWhere('email', 'like', $searchTerm);
                });
            }

            $limit = $request->integer('limit', 20);
            
            // Arquiteto: Reposta a paginação por cursor.
            // Impede esgotamento de memória e mantém a estrutura JSON compatível com o Vue.
            $customers = $query->orderBy('name')->orderBy('id')->cursorPaginate($limit);

            return response()->json($customers);

        } catch (Throwable $e) {
            return response()->json([
                'message' => "CRASH: {$e->getMessage()} | Ficheiro: " . basename($e->getFile()) . " | Linha: {$e->getLine()}",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retrieves an UNLIMITED list of support agents along with their team context.
     * Note: Supporters are typically a small group, so get() is acceptable here.
     *
     * @return JsonResponse
     */
    public function supporters(): JsonResponse
    {
        try {
            $supporters = User::where('role', RoleEnum::SUPPORTER->value)
                ->select('id', 'name', 'email', 'team_id')
                ->with('team')
                ->orderBy('name')
                ->get(); 

            return response()->json($supporters);

        } catch (Throwable $e) {
            return response()->json([
                'message' => "CRASH: {$e->getMessage()} | Ficheiro: " . basename($e->getFile()) . " | Linha: {$e->getLine()}",
                'error' => $e->getMessage()
            ], 500);
        }
    }
}