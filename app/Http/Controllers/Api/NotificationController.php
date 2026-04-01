<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Handle fetching and managing notifications for the mobile app.
 * Architect Note: Replaced standard pagination with cursor pagination to prevent 
 * database stalling on the typically massive notifications table.
 */
class NotificationController extends Controller
{
    use ApiResponser;

    /**
     * Get all notifications for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Architect Note: Substituído paginate(15) por cursorPaginate(15).
        // Evita a query "SELECT COUNT(*)" lenta numa tabela que cresce exponencialmente.
        $notifications = $request->user()->notifications()->cursorPaginate(15);
        
        return $this->successResponse($notifications, 'Notificações carregadas com sucesso.');
    }

    /**
     * Mark a specific notification as read.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return $this->successResponse(null, 'Notificação marcada como lida.');
    }

    /**
     * Mark all unread notifications as read.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->successResponse(null, 'Todas as notificações foram marcadas como lidas.');
    }

    /**
     * Delete a specific notification.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->delete();

        return $this->successResponse(null, 'Notificação eliminada com sucesso.');
    }

    /**
     * Delete all notifications for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $request->user()->notifications()->delete();

        return $this->successResponse(null, 'Todas as notificações foram eliminadas.');
    }
}