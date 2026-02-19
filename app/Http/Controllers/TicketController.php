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
    /**
     * Display a listing of the tickets based on user role.
     *
     * @param Request $request
     * @return Response
     */
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

    /**
     * Show the form for creating a new ticket.
     *
     * @return Response
     */
    public function create(): Response
    {
        return Inertia::render('Tickets/Create');
    }

    /**
     * Store a newly created ticket and its initial message in storage.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ]);

        /**
         * Use a database transaction to ensure both ticket and message are created safely.
         */
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

    /**
     * Display the specified ticket and load the chat interface.
     *
     * @param Request $request
     * @param Ticket $ticket
     * @return Response
     */
    public function show(Request $request, Ticket $ticket): Response
    {
        /**
         * Authorization Check: Prevent customers from viewing tickets they don't own.
         */
        if (! $request->user()->isSupporter() && $ticket->customer_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to this ticket.');
        }

        // Eager load related data to avoid N+1 issues in the chat loop
        $ticket->load(['customer', 'assignee', 'messages.sender']);

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket,
        ]);
    }

    /**
     * Store a new chat message inside a specific ticket.
     *
     * @param Request $request
     * @param Ticket $ticket
     * @return RedirectResponse
     */
    public function storeMessage(Request $request, Ticket $ticket): RedirectResponse
    {
        if (! $request->user()->isSupporter() && $ticket->customer_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'message' => ['required', 'string'],
        ]);

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
        ]);

        return redirect()->back();
    }
}