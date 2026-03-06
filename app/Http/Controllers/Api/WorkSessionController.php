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
     * Provide paginated work session reports with filtering and summary metrics.
     * Maintains strict role-based data isolation.
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

        $query = WorkSession::with('user:id,name,email')
            ->withCount('pauses')
            ->latest('started_at');

        if ($user->isSupporter()) {
            $query->where('user_id', $user->id);
        }

        if ($user->isAdmin() && $request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('started_at', $request->input('date'));
        }

        $totalSeconds = (clone $query)
            ->where('status', WorkSessionStatusEnum::COMPLETED->value)
            ->sum('total_worked_seconds');
        
        $sessions = $query->paginate(15);

        $sessions->getCollection()->transform(function ($session) {
            $hours = $session->total_worked_seconds ? floor($session->total_worked_seconds / 3600) : 0;
            $minutes = $session->total_worked_seconds ? floor(($session->total_worked_seconds % 3600) / 60) : 0;
            
            return [
                'id' => $session->id,
                'user' => $session->user,
                'status' => $session->status,
                'date' => $session->started_at->format('Y-m-d'),
                'started_at' => $session->started_at->format('H:i'),
                'ended_at' => $session->ended_at ? $session->ended_at->format('H:i') : null,
                'pauses_count' => $session->pauses_count,
                'total_time_formatted' => $session->total_worked_seconds ? "{$hours}h {$minutes}m" : '-',
            ];
        });

        $usersList = [];
        if ($user->isAdmin()) {
            $usersList = User::whereIn('role', [RoleEnum::SUPPORTER->value, RoleEnum::ADMIN->value])
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        }

        return $this->successResponse([
            'sessions' => $sessions,
            'users' => $usersList,
            'filters' => $request->only(['user_id', 'date']),
            'summary' => [
                'total_hours' => floor($totalSeconds / 3600),
                'total_minutes' => floor(($totalSeconds % 3600) / 60),
            ]
        ], 'Relatório carregado com sucesso.');
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
            ->whereIn('status', ['active', 'paused'])
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
            $workSession->delete();
            return $this->successResponse(null, 'Sessão eliminada com sucesso.');
        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao eliminar a sessão.', 500);
        }
    }
}