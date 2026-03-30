<?php

namespace App\Http\Controllers;

use App\Services\WorkSessionService;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

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
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function start(Request $request): RedirectResponse
    {
        $this->workSessionService->startSession($request->user());
        return redirect()->back()->with('success', __('work_sessions.started_success'));
    }

    /**
     * Places the current shift on hold.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function pause(Request $request): RedirectResponse
    {
        $this->workSessionService->pauseSession($request->user());
        return redirect()->back()->with('success', __('work_sessions.paused_success'));
    }

    /**
     * Resumes the previously held shift.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function resume(Request $request): RedirectResponse
    {
        $this->workSessionService->resumeSession($request->user());
        return redirect()->back()->with('success', __('work_sessions.resumed_success'));
    }

    /**
     * Concludes the shift and finalizes time tracking.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function end(Request $request): RedirectResponse
    {
        $this->workSessionService->endSession($request->user());
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