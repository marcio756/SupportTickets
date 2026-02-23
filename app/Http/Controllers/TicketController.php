<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    /**
     * Display a listing of the tickets.
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
     * Passes the customer list if the authenticated user is a supporter.
     */
    public function create(Request $request): Response
    {
        $customers = [];

        // If the user is a supporter, fetch all customers to populate the searchable dropdown
        if ($request->user()->isSupporter()) {
            $customers = User::where('role', 'customer') // Fallback to string if enum casting fails, adjust to RoleEnum::CUSTOMER if strictly needed
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        }

        return Inertia::render('Tickets/Create', [
            'customers' => $customers,
        ]);
    }

    /**
     * Store a newly created ticket and its initial message.
     * Handles file uploads and logic for supporters creating tickets on behalf of customers.
     */
    public function store(Request $request): RedirectResponse
    {
        $isSupporter = $request->user()->isSupporter();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'customer_id' => [$isSupporter ? 'required' : 'nullable', 'exists:users,id'],
            'attachment' => ['nullable', 'file', 'max:10240'], // 10MB limit strictly enforced by Laravel
        ]);

        $customerId = $isSupporter ? $validated['customer_id'] : $request->user()->id;

        $ticket = Ticket::create([
            'customer_id' => $customerId,
            'title' => $validated['title'],
            'status' => TicketStatusEnum::OPEN,
            // Automatically assign the ticket to the creator if they are a supporter
            'assigned_to' => $isSupporter ? $request->user()->id : null,
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('attachments', 'public');
        }

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'attachment_path' => $attachmentPath,
        ]);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket created successfully.');
    }

    /**
     * Display the specified ticket.
     */
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

    /**
     * Assigns the ticket to the authenticated supporter.
     */
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

    /**
     * Store a new reply message in the ticket.
     */
    public function storeMessage(Request $request, Ticket $ticket): RedirectResponse
    {
        $user = $request->user();

        // Ensure supporters can only reply if they are actively assigned to this ticket
        if ($user->isSupporter() && $ticket->assigned_to !== $user->id) {
            abort(403, 'You must claim this ticket before replying.');
        } elseif (! $user->isSupporter() && $ticket->customer_id !== $user->id) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'message' => ['required', 'string'],
        ]);

        $message = $ticket->messages()->create([
            'user_id' => $user->id,
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

        // Ensure only the assigned supporter or the owner customer can update the ticket status
        if ($user->isSupporter() && $ticket->assigned_to !== $user->id) {
            abort(403, 'You must be assigned to this ticket to change its status.');
        } elseif (! $user->isSupporter() && $ticket->customer_id !== $user->id) {
            abort(403, 'Unauthorized access.');
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
        $user = $request->user();

        if (! $user->isSupporter()) {
            abort(403, 'Only supporters can deduct time.');
        }

        // Prevent non-assigned supporters from draining the customer's support time by just viewing the ticket
        if ($ticket->assigned_to !== $user->id) {
            return response()->json(['status' => 'not_assigned', 'message' => 'You must claim this ticket to deduct time.']);
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