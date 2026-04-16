<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

/**
 * Controller responsável por gerir as notificações do utilizador na interface Web.
 */
class NotificationController extends Controller
{
    /**
     * Recupera todas as notificações não lidas para o utilizador autenticado.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        /**
         * Architect Note: Calling `Auth::user()->unreadNotifications` without limits 
         * silently executes a fully unbounded `->get()`. For a user with 10,000+ unread alerts,
         * this serializes massive payloads and causes an OutOfMemory PHP Fatal Error.
         * We strictly cap this to 100 results for the UI, keeping the response under a few KB.
         */
        $notifications = Auth::user()
            ->unreadNotifications()
            ->latest() // Architect Warning: Ensure an index on (notifiable_id, read_at, created_at) exists.
            ->limit(100)
            ->get();

        return response()->json($notifications);
    }

    /**
     * Marca uma única notificação como lida (elimina-a) e retorna o ID do ticket associado.
     *
     * @param string $id O identificador único da notificação.
     * @return JsonResponse
     */
    public function markAsRead(string $id): JsonResponse
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $ticketId = $notification->data['ticket_id'];
        
        $notification->delete();

        return response()->json(['ticket_id' => $ticketId]);
    }

    /**
     * Marca múltiplas notificações agrupadas como lidas (elimina-as) e retorna o ID do ticket.
     * Útil quando se lida com notificações agrupadas do mesmo ticket.
     *
     * @param Request $request O request contendo o array de 'ids' das notificações.
     * @return JsonResponse
     */
    public function markBulkAsRead(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return response()->json(['status' => 'error', 'message' => 'No IDs provided'], 400);
        }

        $notifications = Auth::user()->notifications()->whereIn('id', $ids)->get();
        
        if ($notifications->isEmpty()) {
            return response()->json(['status' => 'not_found'], 404);
        }

        // Assumimos que todos os IDs agrupados pertencem ao mesmo ticket.
        $ticketId = $notifications->first()->data['ticket_id'];
        
        Auth::user()->notifications()->whereIn('id', $ids)->delete();

        return response()->json(['ticket_id' => $ticketId]);
    }

    /**
     * Elimina todas as notificações para o utilizador atualmente autenticado.
     *
     * @return JsonResponse
     */
    public function destroyAll(): JsonResponse
    {
        Auth::user()->notifications()->delete();
        return response()->json(['status' => 'success']);
    }

    /**
     * Elimina uma única notificação sem redirecionamento.
     *
     * @param string $id O identificador único da notificação.
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        Auth::user()->notifications()->findOrFail($id)->delete();
        return response()->json(['status' => 'success']);
    }
}