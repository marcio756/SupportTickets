<?php

namespace App\Http\Controllers;

use App\Services\WorkSessionService;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

/**
 * Handles HTTP requests for Work Session management.
 */
class WorkSessionController extends Controller
{
    private WorkSessionService $workSessionService;

    public function __construct(WorkSessionService $workSessionService)
    {
        $this->workSessionService = $workSessionService;
    }

    /**
     * Starts a new shift for the authenticated supporter.
     * Supports both JSON (for Optimistic UI) and HTML responses.
     *
     * @param Request $request
     * @return RedirectResponse|JsonResponse
     */
    public function start(Request $request): RedirectResponse|JsonResponse
    {
        $session = $this->workSessionService->startSession($request->user());
        
        if ($request->wantsJson()) {
            return response()->json(['status' => 'success', 'session' => $session]);
        }
        
        return redirect()->back()->with('success', __('work_sessions.started_success'));
    }

    /**
     * Places the current shift on hold.
     *
     * @param Request $request
     * @return RedirectResponse|JsonResponse
     */
    public function pause(Request $request): RedirectResponse|JsonResponse
    {
        $session = $this->workSessionService->pauseSession($request->user());
        
        if ($request->wantsJson()) {
            return response()->json(['status' => 'success', 'session' => $session]);
        }
        
        return redirect()->back()->with('success', __('work_sessions.paused_success'));
    }

    /**
     * Resumes the previously held shift.
     *
     * @param Request $request
     * @return RedirectResponse|JsonResponse
     */
    public function resume(Request $request): RedirectResponse|JsonResponse
    {
        $session = $this->workSessionService->resumeSession($request->user());
        
        if ($request->wantsJson()) {
            return response()->json(['status' => 'success', 'session' => $session]);
        }
        
        return redirect()->back()->with('success', __('work_sessions.resumed_success'));
    }

    /**
     * Concludes the shift and finalizes time tracking.
     *
     * @param Request $request
     * @return RedirectResponse|JsonResponse
     */
    public function end(Request $request): RedirectResponse|JsonResponse
    {
        $session = $this->workSessionService->endSession($request->user());
        
        if ($request->wantsJson()) {
            return response()->json(['status' => 'success', 'session' => $session]);
        }
        
        return redirect()->back()->with('success', __('work_sessions.ended_success'));
    }

    /**
     * Permanently removes a work session. Authorized for administrators only.
     *
     * @param WorkSession $workSession
     * @return RedirectResponse
     */
    public function destroy(WorkSession $workSession): RedirectResponse
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, __('work_sessions.unauthorized'));
        }

        $this->workSessionService->deleteSession($workSession);

        return redirect()->back()->with('success', __('work_sessions.deleted_success'));
    }
}