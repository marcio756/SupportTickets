<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $query = Ticket::with(['customer', 'assignee']);

        if (! $user->isSupporter()) {
            $query->where('customer_id', $user->id);
        }

        $tickets = $query->latest()->paginate(10);

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Tickets/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ]);

        $ticket = Ticket::create([
            'customer_id' => $request->user()->id,
            'title' => $validated['title'],
            'status' => TicketStatusEnum::OPEN,
        ]);

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
        ]);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket created successfully.');
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        if (! $request->user()->isSupporter() && $ticket->customer_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to this ticket.');
        }

        $ticket->load(['customer', 'assignee', 'messages.sender']);

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket,
        ]);
    }

    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        if (! $request->user()->isSupporter()) {
            abort(403, 'Only supporters can claim tickets.');
        }

        $ticket->update([
            'assigned_to' => $request->user()->id,
        ]);

        return redirect()->back();
    }

    public function storeMessage(Request $request, Ticket $ticket): RedirectResponse
    {
        if (! $request->user()->isSupporter() && $ticket->customer_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'message' => ['required', 'string'],
        ]);

        $message = $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
        ]);

        // Dispatches the WebSocket event to the network
        broadcast(new \App\Events\TicketMessageCreated($message));

        return redirect()->back();
    }

    /**
     * Update the status of a specific ticket.
     */
    public function updateStatus(Request $request, Ticket $ticket): RedirectResponse
    {
        $user = $request->user();

        // Security check: Only the owner or a supporter can modify
        if (! $user->isSupporter() && $ticket->customer_id !== $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', \Illuminate\Validation\Rule::enum(TicketStatusEnum::class)],
        ]);

        $newStatus = $validated['status'];

        // Business Rule: Customers can only transition tickets to RESOLVED
        if (! $user->isSupporter() && $newStatus !== TicketStatusEnum::RESOLVED->value) {
            abort(403, 'Customers can only mark tickets as resolved.');
        }

        $ticket->update([
            'status' => $newStatus
        ]);

        // Optional system alert embedded in the chat
        $ticket->messages()->create([
            'user_id' => $user->id,
            'message' => "🔄 O estado do ticket foi alterado para: " . strtoupper($newStatus),
        ]);

        return redirect()->back();
    }

    /**
     * Handles the regular heartbeat from the frontend to deduct support time.
     * Uses the SupportTimeManager service to perform the logic.
     */
    public function tickTime(Request $request, Ticket $ticket, \App\Services\SupportTimeManager $timeManager): \Illuminate\Http\JsonResponse
    {
        if (! $request->user()->isSupporter()) {
            abort(403, 'Only supporters can deduct time.');
        }

        if ($ticket->status->value !== 'open') {
            return response()->json(['status' => 'not_open']);
        }

        // Delegate the business logic to the specific service
        $remainingSeconds = $timeManager->deductTime($ticket, 5);

        return response()->json([
            'status' => 'success',
            'remaining_seconds' => $remainingSeconds
        ]);
    }
}