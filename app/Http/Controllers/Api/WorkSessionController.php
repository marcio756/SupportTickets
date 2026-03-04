<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WorkSessionService;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * API Controller for work session management.
 */
class WorkSessionController extends Controller
{
    use ApiResponser;

    /**
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

        $sessions = $request->user()->workSessions()
            ->with('pauses')
            ->latest('started_at')
            ->paginate(15);

        return $this->successResponse($sessions, 'Histórico de sessões carregado.');
    }

    /**
     * Retrieve the current open work session.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isSupporter()) {
            return $this->errorResponse('Access restricted to supporters.', 403);
        }

        $session = $user->workSessions()
            ->whereIn('status', ['active', 'paused'])
            ->with('pauses')
            ->first();

        return $this->successResponse($session, 'Sessão atual carregada.');
    }

    /**
     * Start a new work session.
     */
    public function start(Request $request): JsonResponse
    {
        if (!$request->user()->isSupporter()) {
            return $this->errorResponse('Access restricted to supporters.', 403);
        }

        try {
            $session = $this->workSessionService->startSession($request->user()); // Uses Service logic
            return $this->successResponse($session, 'Sessão iniciada com sucesso.', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Pause the current active session.
     */
    public function pause(Request $request): JsonResponse
    {
        try {
            $session = $this->workSessionService->pauseSession($request->user());
            return $this->successResponse($session, 'Sessão pausada.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Resume a previously paused session.
     */
    public function resume(Request $request): JsonResponse
    {
        try {
            $session = $this->workSessionService->resumeSession($request->user());
            return $this->successResponse($session, 'Sessão retomada.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * End the current work session.
     */
    public function stop(Request $request): JsonResponse
    {
        try {
            $session = $this->workSessionService->endSession($request->user());
            return $this->successResponse($session, 'Sessão terminada com sucesso.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}