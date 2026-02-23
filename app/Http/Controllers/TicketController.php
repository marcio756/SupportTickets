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

        // Isolate records based on user role
        if (! $user->isSupporter()) {
            $query->where('customer_id', $user->id);
        }

        // Apply text search filter
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm, $user) {
                $q->where('title', 'like', '%' . $searchTerm . '%');
                
                // Supporters can also search by customer name
                if ($user->isSupporter()) {
                    $q->orWhereHas('customer', function ($customerQuery) use ($searchTerm) {
                        $customerQuery->where('name', 'like', '%' . $searchTerm . '%');
                    });
                }
            });
        }

        // Apply status dropdown filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Apply multiple customers filter
        if ($request->filled('customers') && $user->isSupporter()) {
            $customerIds = is_array($request->customers) ? $request->customers : explode(',', $request->customers);
            $query->whereIn('customer_id', $customerIds);
        }

        // Apply multi-select assignment filter (Supporters only)
        if ($request->filled('assignees') && $user->isSupporter()) {
            $assignees = is_array($request->assignees) ? $request->assignees : explode(',', $request->assignees);
            
            // Usar uma subquery lógica para agrupar as opções "OU"
            $query->where(function ($q) use ($assignees, $user) {
                if (in_array('unassigned', $assignees)) {
                    $q->orWhereNull('assigned_to');
                }
                if (in_array('me', $assignees)) {
                    $q->orWhere('assigned_to', $user->id);
                }
            });
        }

        // Execute query with pagination and preserve query strings
        $tickets = $query->latest()->paginate(10)->withQueryString();

        // Fetch the list of customers to populate the dropdown for supporters
        $customersList = [];
        if ($user->isSupporter()) {
            $customersList = User::where('role', 'customer')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        }

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
            'filters' => $request->only(['search', 'status', 'customers', 'assignees']),
            'customersList' => $customersList,
        ]);
    }

    /**
     * Show the form for creating a new ticket.
     */
    public function create(Request $request): Response
    {
        $customers = [];

        if ($request->user()->isSupporter()) {
            $customers = User::where('role', 'customer') 
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
     */
    public function store(Request $request): RedirectResponse
    {
        $isSupporter = $request->user()->isSupporter();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'customer_id' => [$isSupporter ? 'required' : 'nullable', 'exists:users,id'],
            'attachment' => ['nullable', 'file', 'max:10240'], 
        ]);

        $customerId = $isSupporter ? $validated['customer_id'] : $request->user()->id;

        $ticket = Ticket::create([
            'customer_id' => $customerId,
            'title' => $validated['title'],
            'status' => TicketStatusEnum::OPEN,
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

        if ($user->isSupporter() && $ticket->assigned_to !== $user->id) {
            abort(403, 'You must claim this ticket before replying.');
        } elseif (! $user->isSupporter() && $ticket->customer_id !== $user->id) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'message' => ['required_without:attachment', 'nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:10240'], // 10MB limit
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('attachments', 'public');
        }

        $message = $ticket->messages()->create([
            'user_id' => $user->id,
            'message' => $validated['message'] ?? '',
            'attachment_path' => $attachmentPath,
        ]);

        // Ensure the sender data is loaded before broadcasting to prevent frontend issues
        $message->load('sender');
        broadcast(new \App\Events\TicketMessageCreated($message));

        return redirect()->back();
    }

    /**
     * Update the status of a specific ticket.
     */
    public function updateStatus(Request $request, Ticket $ticket): RedirectResponse
    {
        $user = $request->user();

        if ($user->isSupporter() && $ticket->assigned_to !== $user->id) {
            abort(403, 'You must be assigned to this ticket to change its status.');
        } elseif (! $user->isSupporter() && $ticket->customer_id !== $user->id) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'status' => ['required', 'string', \Illuminate\Validation\Rule::enum(TicketStatusEnum::class)],
        ]);

        $newStatus = $validated['status'];

        if (! $user->isSupporter() && $newStatus !== TicketStatusEnum::RESOLVED->value) {
            abort(403, 'Customers can only mark tickets as resolved.');
        }

        $ticket->update([
            'status' => $newStatus
        ]);

        $message = $ticket->messages()->create([
            'user_id' => $user->id,
            'message' => "🔄 O estado do ticket foi alterado para: " . strtoupper($newStatus),
        ]);

        $message->load('sender');
        broadcast(new \App\Events\TicketMessageCreated($message));

        return redirect()->back();
    }

    /**
     * Handles the regular heartbeat from the frontend to deduct support time.
     */
    public function tickTime(Request $request, Ticket $ticket, \App\Services\SupportTimeManager $timeManager): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if (! $user->isSupporter()) {
            abort(403, 'Only supporters can deduct time.');
        }

        if ($ticket->assigned_to !== $user->id) {
            return response()->json(['status' => 'not_assigned', 'message' => 'You must claim this ticket to deduct time.']);
        }

        if ($ticket->status->value !== 'open') {
            return response()->json(['status' => 'not_open']);
        }

        $remainingSeconds = $timeManager->deductTime($ticket, 5);

        return response()->json([
            'status' => 'success',
            'remaining_seconds' => $remainingSeconds
        ]);
    }
}