<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        /**
         * Architect Note: latest() orders by created_at. On millions of logs, this forces
         * an expensive filesort. We enforce orderByDesc('id') to utilize the primary key index.
         */
        $query = Activity::with(['causer'])->orderByDesc('id');

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

        // Arquitetura: Utilizar DB::table em vez do Eloquent Model impede que o Laravel
        // "hidrate" dezenas de milhares de objetos pesados, o que consome os 512MB de RAM.
        // O ->toArray() converte tudo para arrays básicos e leves para a Cache Store.
        $usersList = Cache::remember('activity_users_list', 300, function () {
            return DB::table('users')
                ->select('id', 'name')
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get()
                ->toArray();
        });

        $events = Cache::remember('activity_events_list', 86400, function () {
            return DB::table('activity_log')
                ->select('event')
                ->whereNotNull('event')
                ->distinct()
                ->pluck('event')
                ->toArray();
        });

        $targets = Cache::remember('activity_targets_list', 86400, function () {
            return DB::table('activity_log')
                ->select('subject_type')
                ->whereNotNull('subject_type')
                ->distinct()
                ->pluck('subject_type')
                ->toArray();
        });

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