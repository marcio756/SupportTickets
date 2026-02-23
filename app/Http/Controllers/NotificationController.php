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
     * Mark a notification as read and return the ticket ID.
     * The requirement states it should "disappear" upon click.
     * * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead($id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $ticketId = $notification->data['ticket_id'];
        
        // Deleting instead of marking as read to make it "disappear"
        $notification->delete();

        return response()->json(['ticket_id' => $ticketId]);
    }

    /**
     * Delete all notifications for the user.
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