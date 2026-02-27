<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Activitylog\Models\Activity;
use App\Models\User;

/**
 * Handles the retrieval of system-wide activity logs via API.
 * Restricted to users with supporter privileges.
 */
class ActivityLogController extends Controller
{
    use ApiResponser;

    /**
     * Display a paginated listing of all system activities with multi-filter support.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isSupporter()) {
            return $this->errorResponse('Unauthorized access to activity logs.', 403);
        }

        $query = Activity::with(['causer'])->latest();

        if ($request->filled('user')) {
            $users = is_array($request->input('user')) ? $request->input('user') : explode(',', $request->input('user'));
            
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
            $events = is_array($request->input('event')) ? $request->input('event') : explode(',', $request->input('event'));
            $query->whereIn('event', $events);
        }

        if ($request->filled('target')) {
            $targets = is_array($request->input('target')) ? $request->input('target') : explode(',', $request->input('target'));
            $query->whereIn('subject_type', $targets);
        }

        if ($startDate = $request->input('date_start')) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate = $request->input('date_end')) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $logs = $query->paginate(15);

        // Fetch required options to populate mobile app dropdowns
        $usersList = User::select('id', 'name')->orderBy('name')->get();
        $events = Activity::select('event')->distinct()->pluck('event');
        $targets = Activity::select('subject_type')->distinct()->pluck('subject_type');

        return $this->successResponse([
            'logs' => $logs,
            'options' => [
                'users' => $usersList,
                'events' => $events,
                'targets' => $targets,
            ]
        ], 'Activity logs retrieved successfully.');
    }
}