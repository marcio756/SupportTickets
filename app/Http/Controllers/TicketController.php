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

        // Dispara o evento WebSockets para a rede
        broadcast(new \App\Events\TicketMessageCreated($message));

        return redirect()->back();
    }

    public function tickTime(Request $request, Ticket $ticket): \Illuminate\Http\JsonResponse
    {
        if (! $request->user()->isSupporter()) {
            abort(403, 'Only supporters can deduct time.');
        }

        if ($ticket->status->value !== 'open') {
            return response()->json(['status' => 'not_open']);
        }

        $customer = $ticket->customer;

        if ($customer->daily_support_seconds > 0) {
            $newTime = max(0, $customer->daily_support_seconds - 5);
            
            $customer->update([
                'daily_support_seconds' => $newTime
            ]);

            broadcast(new \App\Events\SupportTimeUpdated($ticket->id, $newTime));
        }

        return response()->json([
            'status' => 'success',
            'remaining_seconds' => $customer->daily_support_seconds
        ]);
    }
}