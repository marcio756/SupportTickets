<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Handles the display of the system-wide activity logs.
 * Restricted to users with supporter privileges.
 */
class ActivityLogController extends Controller
{
    /**
     * Display a paginated listing of all system activities.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        if (! $request->user()->isSupporter()) {
            abort(403, 'Unauthorized access to activity logs.');
        }

        $logs = Activity::with(['causer'])
            ->latest()
            ->paginate(15);

        return Inertia::render('ActivityLog/Index', [
            'logs' => $logs,
        ]);
    }
}