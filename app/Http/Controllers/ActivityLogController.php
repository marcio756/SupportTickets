<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use App\Models\User;

/**
 * Handles the display of the system-wide activity logs.
 * Restricted to users with admin privileges.
 */
class ActivityLogController extends Controller
{
    /**
     * Display a paginated listing of all system activities with multi-filter support.
     * Supports intervals, system queries, and multiple event/target isolation.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        // Enforce that only administrators can access system logs
        if (! $request->user()->isAdmin()) {
            abort(403, 'Unauthorized access to activity logs.');
        }

        $query = Activity::with(['causer'])->latest();

        if ($request->filled('user')) {
            $users = (array) $request->input('user');
            
            $query->where(function ($q) use ($users) {
                $hasSystem = in_array('system', $users);
                $userIds = array_filter($users, fn($id) => $id !== 'system');

                if ($hasSystem) {
                    $q->whereNull('causer_id');
                }

                if (!empty($userIds)) {
                    if ($hasSystem) {
                        $q->orWhereIn('causer_id', $userIds);
                    } else {
                        $q->whereIn('causer_id', $userIds);
                    }
                }
            });
        }

        if ($request->filled('event')) {
            $query->whereIn('event', (array) $request->input('event'));
        }

        if ($request->filled('target')) {
            $query->whereIn('subject_type', (array) $request->input('target'));
        }

        if ($startDate = $request->input('date_start')) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate = $request->input('date_end')) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $logs = $query->paginate(15)->withQueryString()->through(fn ($log) => [
            'id'           => $log->id,
            'description'  => $log->description,
            'event'        => $log->event,
            'subject_type' => $log->subject_type,
            'subject_id'   => $log->subject_id,
            'properties'   => $log->properties,
            'causer'       => $log->causer ? [
                'id'   => $log->causer->id,
                'name' => $log->causer->name,
            ] : null,
            'created_at'   => $log->created_at,
        ]);

        $usersList = User::select('id', 'name')->orderBy('name')->get();
        $events = Activity::select('event')->distinct()->pluck('event');
        $targets = Activity::select('subject_type')->distinct()->pluck('subject_type');

        return Inertia::render('ActivityLog/Index', [
            'logs' => $logs,
            'filters' => $request->only(['user', 'event', 'target', 'date_start', 'date_end']),
            'options' => [
                'users' => $usersList,
                'events' => $events,
                'targets' => $targets,
            ]
        ]);
    }
}