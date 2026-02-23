<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get all unread notifications for the authenticated user.
     * * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        return response()->json(Auth::user()->unreadNotifications);
    }

    /**
     * Mark a single notification as read (delete it) and return the associated ticket ID.
     * * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead($id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $ticketId = $notification->data['ticket_id'];
        
        $notification->delete();

        return response()->json(['ticket_id' => $ticketId]);
    }

    /**
     * Mark multiple grouped notifications as read (delete them) and return the ticket ID.
     * Useful when handling bundled notifications from the same ticket.
     * * @param Request $request Expects an array of notification 'ids'.
     * @return \Illuminate\Http\JsonResponse
     */
    public function markBulkAsRead(Request $request)
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return response()->json(['status' => 'error', 'message' => 'No IDs provided'], 400);
        }

        $notifications = Auth::user()->notifications()->whereIn('id', $ids)->get();
        
        if ($notifications->isEmpty()) {
            return response()->json(['status' => 'not_found'], 404);
        }

        // We assume all grouped IDs belong to the same ticket, so retrieving the first is safe.
        $ticketId = $notifications->first()->data['ticket_id'];
        
        Auth::user()->notifications()->whereIn('id', $ids)->delete();

        return response()->json(['ticket_id' => $ticketId]);
    }

    /**
     * Delete all notifications for the currently authenticated user.
     * * @return \Illuminate\Http\JsonResponse
     */
    public function destroyAll()
    {
        Auth::user()->notifications()->delete();
        return response()->json(['status' => 'success']);
    }

    /**
     * Delete a single notification.
     * * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        Auth::user()->notifications()->findOrFail($id)->delete();
        return response()->json(['status' => 'success']);
    }
}