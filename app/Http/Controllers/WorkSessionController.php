<?php

namespace App\Http\Controllers;

use App\Services\WorkSessionService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

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
        return redirect()->back()->with('success', 'Work session started.');
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
        return redirect()->back()->with('success', 'Work session paused.');
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
        return redirect()->back()->with('success', 'Work session resumed.');
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
        return redirect()->back()->with('success', 'Work session ended.');
    }
}