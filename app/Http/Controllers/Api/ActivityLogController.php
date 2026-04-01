<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;
use App\Models\User;

/**
 * Handles the retrieval of system-wide activity logs via API.
 * Restricted to users with admin or supporter privileges.
 * Architect Note: Highly optimized to handle millions of audit logs without memory crashes.
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
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isSupporter()) {
            return $this->errorResponse('Unauthorized access to activity logs.', 403);
        }

        $query = Activity::with(['causer']);

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

        // Usa simplePaginate para evitar o COUNT(*) numa tabela de logs massiva.
        $logs = $query->latest('id')->simplePaginate(15);

        // Architect Note: Otimização crítica de Memória e Base de Dados.
        // O distinct() numa tabela com milhões de logs paralisa o servidor. 
        // Fazemos cache destes valores (que são estruturais do código) por 24 horas.
        $events = Cache::remember('activity_logs:options:events', 86400, function () {
            return Activity::select('event')->distinct()->pluck('event');
        });

        $targets = Cache::remember('activity_logs:options:targets', 86400, function () {
            return Activity::select('subject_type')->distinct()->pluck('subject_type');
        });

        // Architect Note: Limite de segurança imposto.
        // Fazer User::get() num sistema com milhões de utilizadores causaria um crash 
        // por falta de memória PHP (OOM) e rebentaria com o browser ao tentar parsear um JSON de vários Megabytes.
        $usersList = User::select('id', 'name')->orderBy('name')->limit(500)->get();

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