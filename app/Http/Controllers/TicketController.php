<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SupportTimeManager;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\Rule;

/**
 * Manages the core ticketing operations for the Web interface.
 * Delegates business logic to TicketService.
 */
class TicketController extends Controller
{
    /**
     * @param TicketService $ticketService
     */
    public function __construct(
        protected TicketService $ticketService
    ) {}

    /**
     * Display a paginated listing of the tickets based on user role and applied filters.
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

        $this->applyIndexFilters($query, $request, $user);

        $tickets = $query->latest()->paginate(10)->withQueryString();

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
     * Applies search and dropdown filters to the ticket query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     * @param User $user
     * @return void
     */
    private function applyIndexFilters($query, Request $request, User $user): void
    {
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm, $user) {
                $q->where('title', 'like', '%' . $searchTerm . '%');
                
                if ($user->isSupporter()) {
                    $q->orWhereHas('customer', function ($customerQuery) use ($searchTerm) {
                        $customerQuery->where('name', 'like', '%' . $searchTerm . '%');
                    });
                }
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customers') && $user->isSupporter()) {
            $customerIds = is_array($request->customers) ? $request->customers : explode(',', $request->customers);
            $query->whereIn('customer_id', $customerIds);
        }

        if ($request->filled('assignees') && $user->isSupporter()) {
            $assignees = is_array($request->assignees) ? $request->assignees : explode(',', $request->assignees);
            
            $query->where(function ($q) use ($assignees, $user) {
                if (in_array('unassigned', $assignees)) {
                    $q->orWhereNull('assigned_to');
                }
                if (in_array('me', $assignees)) {
                    $q->orWhere('assigned_to', $user->id);
                }
            });
        }
    }

    /**
     * Show the form for creating a new ticket.
     *
     * @param Request $request
     * @return Response
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
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'customer_id' => [$request->user()->isSupporter() ? 'required' : 'nullable', 'exists:users,id'],
            'attachment' => ['nullable', 'file', 'max:10240'], 
        ]);

        $ticket = $this->ticketService->createTicket(
            $request->user(), 
            $validated, 
            $request->file('attachment')
        );

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket created successfully.');
    }

    /**
     * Display the specified ticket.
     *
     * @param Request $request
     * @param Ticket $ticket
     * @return Response
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
     *
     * @param Request $request
     * @param Ticket $ticket
     * @return RedirectResponse
     */
    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->ticketService->assignTicket($request->user(), $ticket);

        return redirect()->back();
    }

    /**
     * Store a new reply message in the ticket.
     *
     * @param Request $request
     * @param Ticket $ticket
     * @return RedirectResponse
     */
    public function storeMessage(Request $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required_without:attachment', 'nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:10240'], 
        ]);

        $this->ticketService->sendMessage(
            $request->user(),
            $ticket,
            $validated,
            $request->file('attachment')
        );

        return redirect()->back();
    }

    /**
     * Update the status of a specific ticket.
     *
     * @param Request $request
     * @param Ticket $ticket
     * @return RedirectResponse
     */
    public function updateStatus(Request $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::enum(TicketStatusEnum::class)],
        ]);

        $this->ticketService->updateStatus($request->user(), $ticket, $validated['status']);

        return redirect()->back();
    }

    /**
     * Handles the regular heartbeat from the frontend to deduct support time.
     *
     * @param Request $request
     * @param Ticket $ticket
     * @param SupportTimeManager $timeManager
     * @return JsonResponse
     */
    public function tickTime(Request $request, Ticket $ticket, SupportTimeManager $timeManager): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSupporter()) {
            abort(403, 'Only supporters can deduct time.');
        }

        if ($ticket->assigned_to !== $user->id) {
            return response()->json(['status' => 'not_assigned', 'message' => 'You must claim this ticket to deduct time.']);
        }

        if ($ticket->status !== TicketStatusEnum::IN_PROGRESS) {
            return response()->json(['status' => 'not_in_progress']);
        }

        $remainingSeconds = $timeManager->deductTime($ticket, 5);

        return response()->json([
            'status' => 'success',
            'remaining_seconds' => $remainingSeconds
        ]);
    }

    /**
     * Delete the ticket after password verification.
     *
     * @param Request $request
     * @param Ticket $ticket
     * @return RedirectResponse
     */
    public function destroy(Request $request, Ticket $ticket): RedirectResponse
    {
        if (!$request->user()->isSupporter()) {
            abort(403);
        }

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $ticket->delete();

        return redirect()->route('tickets.index')->with('success', 'Ticket deleted successfully.');
    }
}