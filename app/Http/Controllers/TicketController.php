<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Enums\TicketStatusEnum;
use App\Enums\WorkSessionStatusEnum;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SupportTimeManager;
use App\Services\TicketService;
use App\Traits\ChecksWorkSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;

/**
 * Manages the core ticketing operations for the Web interface.
 * Delegates business logic to TicketService.
 */
class TicketController extends Controller
{
    use ChecksWorkSession;

    /**
     * @param TicketService $ticketService
     */
    public function __construct(
        protected TicketService $ticketService
    ) {}

    /**
     * Display a paginated listing of the tickets based on user role and applied filters.
     * Evaluates active sessions to restrict access for off-duty supporters.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $workSessionStatus = $this->getWorkSessionStatus($user);

        if ($user->isSupporter() && !$user->isAdmin() && $workSessionStatus !== WorkSessionStatusEnum::ACTIVE->value) {
            return Inertia::render('Tickets/Index', [
                'tickets' => [
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 10,
                    'total' => 0,
                ],
                'filters' => $request->only(['search', 'status', 'source', 'customers', 'assignees', 'tags']),
                'customersList' => [],
                'availableTags' => [],
                'workSessionStatus' => $workSessionStatus,
            ]);
        }

        /**
         * Architect Note: Eager loading is maintained to prevent N+1 queries.
         * Only essential relationships for the listing are loaded.
         */
        $query = Ticket::with(['customer:id,name,email', 'assignee:id,name', 'tags:id,name,color']);

        if (! $user->isStaff()) {
            $query->where('customer_id', $user->id);
        }

        $this->applyIndexFilters($query, $request, $user);

        $tickets = $query->latest()->paginate(10)->withQueryString();

        /**
         * Architect Note: Fetching millions of users with `get()` causes memory exhaustion.
         * For the initial load, we return an empty array. The frontend should be updated
         * to use an async autocomplete endpoint (e.g., /api/customers/search) for the CustomerSelector.
         */
        $customersList = [];
        $availableTags = [];
        
        if ($user->isStaff()) {
            // Tags are usually a small dataset, safe to cache and load entirely.
            $availableTags = Cache::remember('tags_list_all', now()->addDay(), function () {
                return Tag::select('id', 'name', 'color')->orderBy('name')->get();
            });
        }

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
            'filters' => $request->only(['search', 'status', 'source', 'customers', 'assignees', 'tags']),
            'customersList' => $customersList,
            'availableTags' => $availableTags,
            'workSessionStatus' => $workSessionStatus,
        ]);
    }

    /**
     * Applies search and dropdown filters to the ticket query.
     * Prevents full table scans by optimizing LIKE clauses.
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
                /**
                 * Architect Note: Removed leading wildcard (%) to allow the database to use B-Tree indexes.
                 * If full-text search is strictly required inside the string, consider Laravel Scout or MySQL/PostgreSQL Full-Text Indexes.
                 */
                $q->where('title', 'like', $searchTerm . '%');
                
                if ($user->isStaff()) {
                    $q->orWhereHas('customer', function ($customerQuery) use ($searchTerm) {
                        $customerQuery->where('name', 'like', $searchTerm . '%');
                    });
                }
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        if ($request->filled('customers') && $user->isStaff()) {
            $customerIds = is_array($request->customers) ? $request->customers : explode(',', $request->customers);
            $query->whereIn('customer_id', $customerIds);
        }

        if ($request->filled('assignees') && $user->isStaff()) {
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

        if ($request->filled('tags')) {
            $tagIds = is_array($request->tags) ? $request->tags : explode(',', $request->tags);
            $query->whereHas('tags', function ($q) use ($tagIds) {
                $q->whereIn('tags.id', $tagIds);
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
        $availableTags = [];

        if ($request->user()->isStaff()) {
            // Architect Note: Empty array provided. Frontend must use async search.
            $customers = [];
                
            $availableTags = Cache::remember('tags_list_all', now()->addDay(), function () {
                return Tag::select('id', 'name', 'color')->orderBy('name')->get();
            });
        }

        return Inertia::render('Tickets/Create', [
            'customers' => $customers,
            'availableTags' => $availableTags,
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
            'customer_id' => [$request->user()->isStaff() ? 'required' : 'nullable', 'exists:users,id'],
            'attachment' => ['nullable', 'file', 'max:10240'], 
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
        ]);

        $ticket = $this->ticketService->createTicket(
            $request->user(), 
            $validated, 
            $request->file('attachment')
        );

        return redirect()->route('tickets.show', $ticket)
            ->with('success', __('tickets.created_success'));
    }

    /**
     * Display the specified ticket.
     *
     * @param Request $request
     * @param Ticket $ticket
     * @return Response|RedirectResponse
     */
    public function show(Request $request, Ticket $ticket): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->isStaff()) {
            if (!$user->isAdmin() && $this->getWorkSessionStatus($user) !== WorkSessionStatusEnum::ACTIVE->value) {
                return redirect()->route('tickets.index')->with('error', __('tickets.shift_required'));
            }
        } elseif ($ticket->customer_id !== $user->id) {
            abort(403, __('tickets.unauthorized_access'));
        }

        $ticket->load(['customer:id,name,email', 'assignee:id,name', 'messages.sender:id,name,role', 'tags', 'participants:id,name']);

        $availableTags = [];
        $mentionableUsers = [];

        if ($user->isStaff()) {
            $availableTags = Cache::remember('tags_list_all', now()->addDay(), function () {
                return Tag::select('id', 'name', 'color')->orderBy('name')->get();
            });
            
            $mentionableUsers = Cache::remember('mentionable_staff_users', now()->addHours(4), function () {
                return User::whereIn('role', ['supporter', 'admin'])
                    ->select('id', 'name', 'role')
                    ->orderBy('name')
                    ->get();
            });
        }

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket,
            'availableTags' => $availableTags,
            'mentionableUsers' => $mentionableUsers,
        ]);
    }

    /**
     * Sync the tags for a specific ticket.
     *
     * @param Request $request
     * @param Ticket $ticket
     * @return RedirectResponse
     */
    public function syncTags(Request $request, Ticket $ticket): RedirectResponse
    {
        if (! $request->user()->isStaff()) {
            abort(403, __('tickets.unauthorized_action'));
        }

        $validated = $request->validate([
            'tags' => ['array'],
            'tags.*' => ['exists:tags,id'],
        ]);

        $ticket->tags()->sync($validated['tags'] ?? []);

        return redirect()->back()->with('success', __('tickets.tags_updated_success'));
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
            'mentions' => ['nullable', 'array'],
            'mentions.*' => ['string'],
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

        if (! $user->isStaff()) {
            abort(403, __('tickets.staff_only_time'));
        }

        Gate::authorize('update', $ticket);

        if ($ticket->status !== TicketStatusEnum::IN_PROGRESS->value) {
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
        if (!$request->user()->isStaff()) {
            abort(403);
        }

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $ticket->delete();

        return redirect()->route('tickets.index')->with('success', __('tickets.deleted_success'));
    }
}