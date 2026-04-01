<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Enums\WorkSessionStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\WorkSessionService;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * API Controller for work session management.
 * Handled with high performance cursor pagination for large datasets.
 */
class WorkSessionController extends Controller
{
    use ApiResponser;

    /**
     * Initializes the controller with the necessary service.
     *
     * @param WorkSessionService $workSessionService
     */
    public function __construct(
        protected WorkSessionService $workSessionService
    ) {}

    /**
     * List work session history for the authenticated supporter.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->isSupporter()) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        // Using cursorPaginate to prevent O(N) performance issues on millions of rows
        $sessions = $request->user()->workSessions()
            ->with('pauses')
            ->orderByDesc('started_at')
            ->cursorPaginate(15);

        return $this->successResponse($sessions, 'Histórico de sessões carregado.');
    }

    /**
     * Provide paginated work session reports with filtering and summary metrics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function reports(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isCustomer()) {
            return $this->errorResponse('Unauthorized access.', 403);
        }

        $reportData = $this->workSessionService->getReportsData($user, $request->all());

        return $this->successResponse($reportData, 'Relatório carregado com sucesso.');
    }

    /**
     * Retrieve the current open work session.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isSupporter()) {
            return $this->errorResponse('Access restricted to supporters.', 403);
        }

        $session = $user->workSessions()
            ->whereIn('status', [WorkSessionStatusEnum::ACTIVE->value, WorkSessionStatusEnum::PAUSED->value])
            ->with('pauses') // Crucial for dynamic accessor calculation
            ->first();

        return $this->successResponse($session, 'Sessão atual carregada.');
    }

    /**
     * Start a new work session.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function start(Request $request): JsonResponse
    {
        if (!$request->user()->isSupporter()) {
            return $this->errorResponse('Access restricted to supporters.', 403);
        }

        try {
            $session = $this->workSessionService->startSession($request->user());
            $session->load('pauses'); // Load relation so dynamic time calculation succeeds
            return $this->successResponse($session, 'Sessão iniciada com sucesso.', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Pause the current active session.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function pause(Request $request): JsonResponse
    {
        try {
            $session = $this->workSessionService->pauseSession($request->user());
            $session->load('pauses'); // Refresh to include the new pause
            return $this->successResponse($session, 'Sessão pausada.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Resume a previously paused session.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function resume(Request $request): JsonResponse
    {
        try {
            $session = $this->workSessionService->resumeSession($request->user());
            $session->load('pauses'); // Refresh to close the pause
            return $this->successResponse($session, 'Sessão retomada.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * End the current work session.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function end(Request $request): JsonResponse
    {
        try {
            $session = $this->workSessionService->endSession($request->user());
            $session->load('pauses');
            return $this->successResponse($session, 'Sessão terminada com sucesso.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Delete a work session log (Admin only).
     *
     * @param WorkSession $workSession
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy(WorkSession $workSession, Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->errorResponse('Apenas administradores podem eliminar sessões.', 403);
        }

        try {
            $this->workSessionService->deleteSession($workSession);
            return $this->successResponse(null, 'Sessão eliminada com sucesso.');
        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao eliminar a sessão.', 500);
        }
    }
}