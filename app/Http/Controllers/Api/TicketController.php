<?php

namespace App\Http\Controllers\Api;

use App\Enums\TicketStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SupportTimeManager;
use App\Services\TicketService;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Full API Controller for managing Support Tickets.
 * Delegates business logic to TicketService and wraps errors in ApiResponser format.
 * Optimized for high performance and scalability with cursor pagination and caching.
 */
class TicketController extends Controller
{
    use ApiResponser;

    /**
     * Cache duration in seconds (1 hour).
     * @var int
     */
    protected const CACHE_TTL = 3600;

    /**
     * Constructor to inject dependencies.
     *
     * @param TicketService $ticketService
     */
    public function __construct(
        protected TicketService $ticketService
    ) {}

    /**
     * List all tickets relevant to the authenticated user, applying search and filters.
     * Uses cursorPaginate to ensure constant performance regardless of table size.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = Ticket::with(['customer', 'assignee', 'tags', 'participants']);

        if (! $user->isStaff()) {
            $query->where('customer_id', $user->id);
        }

        $this->applyIndexFilters($query, $request, $user);

        // cursorPaginate is significantly faster than paginate() for large datasets 
        // as it avoids the expensive SELECT COUNT(*) query.
        $tickets = $query->latest('id')->cursorPaginate(15);

        return TicketResource::collection($tickets);
    }

    /**
     * Applies search and dropdown filters to the ticket query.
     * Logic preserved from the original implementation to maintain site features.
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
                
                if ($user->isStaff()) {
                    $q->orWhereHas('customer', function ($customerQuery) use ($searchTerm) {
                        $customerQuery->where('name', 'like', '%' . $searchTerm . '%');
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

        if ($request->filled('tags') && $user->isStaff()) {
            $tags = is_array($request->tags) ? $request->tags : explode(',', $request->tags);
            $query->whereHas('tags', function ($q) use ($tags) {
                $q->whereIn('tags.id', $tags);
            });
        }
    }

    /**
     * Store a newly created ticket and its initial message via API.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $isStaff = $request->user()->isStaff();

        $validated = $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'message'      => ['required', 'string'],
            'customer_id'  => [
                $isStaff ? 'required_without:sender_email' : 'nullable', 
                'exists:users,id'
            ],
            'sender_email' => [
                $isStaff ? 'required_without:customer_id' : 'prohibited', 
                'email', 
                'nullable'
            ],
            'attachment'   => ['nullable', 'file', 'max:10240'], 
        ]);

        try {
            $ticket = $this->ticketService->createTicket(
                $request->user(),
                $validated,
                $request->file('attachment')
            );

            return $this->successResponse(
                new TicketResource($ticket->load(['customer', 'assignee', 'messages.sender', 'tags', 'participants'])), 
                'Ticket created successfully.', 
                201
            );
        } catch (\Exception $e) {
            $code = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return $this->errorResponse($e->getMessage(), $code);
        }
    }

    /**
     * Display a specific ticket thread.
     * Uses Redis/Cache to avoid database overhead on high-traffic threads.
     *
     * @param Ticket $ticket
     * @return TicketResource
     */
    public function show(Ticket $ticket): TicketResource
    {
        Gate::authorize('view', $ticket);

        $cacheKey = "ticket_api_show_{$ticket->id}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($ticket) {
            return $ticket->load(['customer', 'assignee', 'messages.sender', 'tags', 'participants']);
        });

        return new TicketResource($data);
    }

    /**
     * Delete a specific ticket via API.
     *
     * @param Ticket $ticket
     * @return JsonResponse
     */
    public function destroy(Ticket $ticket): JsonResponse
    {
        Gate::authorize('delete', $ticket);

        try {
            $ticket->delete();
            $this->clearTicketCache($ticket->id);

            return $this->successResponse(
                null,
                'Ticket deleted successfully.'
            );
        } catch (\Exception $e) {
            $code = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return $this->errorResponse($e->getMessage(), $code);
        }
    }

    /**
     * Assigns the ticket to the authenticated supporter via API.
     *
     * @param Request $request
     * @param Ticket $ticket
     * @return JsonResponse
     */
    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        try {
            $ticket = $this->ticketService->assignTicket($request->user(), $ticket);
            $this->clearTicketCache($ticket->id);

            return $this->successResponse(
                new TicketResource($ticket->load(['customer', 'assignee', 'tags', 'participants'])),
                'Ticket claimed successfully.'
            );
        } catch (\Exception $e) {
            $code = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return $this->errorResponse($e->getMessage(), $code);
        }
    }

    /**
     * Update the status of a specific ticket via API.
     *
     * @param Request $request
     * @param Ticket $ticket
     * @return JsonResponse
     */
    public function updateStatus(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::enum(TicketStatusEnum::class)],
        ]);

        try {
            $ticket = $this->ticketService->updateStatus($request->user(), $ticket, $validated['status']);
            $this->clearTicketCache($ticket->id);

            return $this->successResponse(
                new TicketResource($ticket->load(['customer', 'assignee', 'messages.sender', 'tags', 'participants'])),
                'Ticket status updated.'
            );
        } catch (\Exception $e) {
            $code = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return $this->errorResponse($e->getMessage(), $code);
        }
    }

    /**
     * Store a new message on the ticket thread and deduct time if necessary.
     *
     * @param Request $request
     * @param Ticket $ticket
     * @return JsonResponse
     */
    public function storeMessage(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('update', $ticket);

        $validated = $request->validate([
            'message' => 'required_without:attachment|nullable|string',
            'attachment' => 'nullable|file|max:10240',
            'mentions' => ['nullable', 'array'],
            'mentions.*' => ['string'],
        ]);

        try {
            $this->ticketService->sendMessage(
                $request->user(),
                $ticket,
                $validated,
                $request->file('attachment')
            );
            
            $this->clearTicketCache($ticket->id);

            return $this->successResponse(
                new TicketResource($ticket->load(['customer', 'assignee', 'messages.sender', 'tags', 'participants'])), 
                'Message sent successfully.'
            );
        } catch (\Exception $e) {
            $code = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return $this->errorResponse($e->getMessage(), $code);
        }
    }

    /**
     * API Heartbeat to deduct support time.
     *
     * @param Request $request
     * @param Ticket $ticket
     * @param SupportTimeManager $timeManager
     * @return JsonResponse
     */
    public function tickTime(Request $request, Ticket $ticket, SupportTimeManager $timeManager): JsonResponse
    {
        $user = $request->user();

        if (!$user->isStaff()) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        Gate::authorize('update', $ticket);

        if ($ticket->status !== TicketStatusEnum::IN_PROGRESS->value) {
            return $this->errorResponse('Ticket is not in progress.', 400);
        }

        $remainingSeconds = $timeManager->deductTime($ticket, 5);

        return $this->successResponse(
            ['remaining_seconds' => $remainingSeconds],
            'Time deducted successfully.'
        );
    }

    /**
     * Sync tags for a specific ticket via API.
     *
     * @param Request $request
     * @param Ticket $ticket
     * @return JsonResponse
     */
    public function syncTags(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('update', $ticket);

        $validated = $request->validate([
            'tags' => ['array'],
            'tags.*' => ['exists:tags,id'],
        ]);

        try {
            $ticket->tags()->sync($validated['tags'] ?? []);
            $this->clearTicketCache($ticket->id);

            return $this->successResponse(
                new TicketResource($ticket->load(['customer', 'assignee', 'messages.sender', 'tags', 'participants'])),
                'Tags synchronized successfully.'
            );
        } catch (\Exception $e) {
            $code = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return $this->errorResponse($e->getMessage(), $code);
        }
    }

    /**
     * Helper to clear the cache when a ticket is updated.
     *
     * @param int $id
     * @return void
     */
    protected function clearTicketCache(int $id): void
    {
        Cache::forget("ticket_api_show_{$id}");
    }
}