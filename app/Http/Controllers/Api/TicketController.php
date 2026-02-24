<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\AttachmentService;
use App\Services\SupportTimeManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Enhanced API Controller with Authorization and Time Tracking
 */
class TicketController extends Controller
{
    /**
     * List all tickets relevant to the authenticated user
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $tickets = Ticket::with(['customer', 'assignee'])
            ->when($user->isCustomer(), fn($q) => $q->where('customer_id', $user->id))
            ->when($user->isSupporter(), fn($q) => $q->where('assigned_to', $user->id))
            ->latest()
            ->paginate(15);

        return TicketResource::collection($tickets);
    }

    /**
     * Display a specific ticket thread
     */
    public function show(Ticket $ticket): TicketResource
    {
        Gate::authorize('view', $ticket);

        return new TicketResource($ticket->load(['customer', 'assignee', 'messages.sender']));
    }

    /**
     * Send a message and deduct time if necessary
     */
    public function sendMessage(Request $request, Ticket $ticket, AttachmentService $attachmentService): JsonResponse
    {
        Gate::authorize('update', $ticket);

        $request->validate([
            'message' => 'required|string',
            'attachment' => 'nullable|file|max:10240',
        ]);

        $user = $request->user();

        if ($user->isCustomer() && $user->daily_support_seconds <= 0) {
            return response()->json(['message' => 'Sem tempo de suporte disponível.'], 403);
        }

        $attachmentPath = $request->hasFile('attachment') 
            ? $attachmentService->store($request->file('attachment')) 
            : null;

        $ticket->messages()->create([
            'user_id' => $user->id,
            'message' => $request->message,
            'attachment_path' => $attachmentPath,
        ]);

        return response()->json([
            'message' => 'Mensagem enviada com sucesso.',
            'data' => new TicketResource($ticket->load('messages.sender'))
        ]);
    }

    /**
     * API Heartbeat to deduct support time (Matches web functionality)
     *
     * @param Ticket $ticket
     * @param SupportTimeManager $timeManager
     * @return JsonResponse
     */
    public function tickTime(Ticket $ticket, SupportTimeManager $timeManager): JsonResponse
    {
        // Only supporters can deduct time
        if (!auth()->user()->isSupporter()) {
            abort(403);
        }

        Gate::authorize('update', $ticket);

        // Deduct 5 seconds as per project standard
        $remainingSeconds = $timeManager->deductTime($ticket, 5);

        return response()->json([
            'status' => 'success',
            'remaining_seconds' => $remainingSeconds
        ]);
    }
}