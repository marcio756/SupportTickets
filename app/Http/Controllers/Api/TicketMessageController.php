<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Handles asynchronous data fetching for Ticket communications.
 */
class TicketMessageController extends Controller
{
    /**
     * Fetch paginated messages for a specific ticket.
     * Facilitates infinite scrolling on the frontend.
     *
     * @param Request $request
     * @param Ticket $ticket
     * @return JsonResponse
     */
    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        // Basic authorization check
        if (!$user->isStaff() && $ticket->customer_id !== $user->id) {
            abort(403, 'Unauthorized access.');
        }

        /**
         * Architect Note: Cursor pagination is highly recommended for real-time chat
         * interfaces as it prevents duplicate records when new messages arrive.
         */
        $messages = $ticket->messages()
            ->with('sender:id,name,role')
            ->latest()
            ->cursorPaginate(20);

        return response()->json($messages);
    }
}