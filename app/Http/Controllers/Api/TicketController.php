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
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Full API Controller for managing Support Tickets.
 * Delegates business logic to TicketService and wraps errors in ApiResponser format.
 */
class TicketController extends Controller
{
    use ApiResponser;

    /**
     * @param TicketService $ticketService
     */
    public function __construct(
        protected TicketService $ticketService
    ) {}

    /**
     * List all tickets relevant to the authenticated user, applying search and filters.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = Ticket::with(['customer', 'assignee']);

        if ($user->isCustomer()) {
            $query->where('customer_id', $user->id);
        }

        // Aplica os filtros enviados pela App Móvel (Query Parameters)
        $this->applyIndexFilters($query, $request, $user);

        $tickets = $query->latest()->paginate(15);

        return TicketResource::collection($tickets);
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
     * Store a newly created ticket and its initial message via API
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'customer_id' => [$request->user()->isSupporter() ? 'required' : 'nullable', 'exists:users,id'],
            'attachment' => ['nullable', 'file', 'max:10240'], 
        ]);

        try {
            $ticket = $this->ticketService->createTicket(
                $request->user(),
                $validated,
                $request->file('attachment')
            );

            return $this->successResponse(
                new TicketResource($ticket->load(['customer', 'assignee', 'messages.sender'])), 
                'Ticket criado com sucesso.', 
                201
            );
        } catch (\Exception $e) {
            $code = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return $this->errorResponse($e->getMessage(), $code);
        }
    }

    /**
     * Display a specific ticket thread
     *
     * @param Ticket $ticket
     * @return TicketResource
     */
    public function show(Ticket $ticket): TicketResource
    {
        Gate::authorize('view', $ticket);

        return new TicketResource($ticket->load(['customer', 'assignee', 'messages.sender']));
    }

    /**
     * Delete a specific ticket via API
     *
     * @param Ticket $ticket
     * @return JsonResponse
     */
    public function destroy(Ticket $ticket): JsonResponse
    {
        Gate::authorize('delete', $ticket);

        try {
            $ticket->delete();

            return $this->successResponse(
                null,
                'Ticket eliminado com sucesso.'
            );
        } catch (\Exception $e) {
            $code = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return $this->errorResponse($e->getMessage(), $code);
        }
    }

    /**
     * Assigns the ticket to the authenticated supporter via API
     *
     * @param Request $request
     * @param Ticket $ticket
     * @return JsonResponse
     */
    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        try {
            $ticket = $this->ticketService->assignTicket($request->user(), $ticket);

            return $this->successResponse(
                new TicketResource($ticket->load(['customer', 'assignee'])),
                'Ticket reivindicado com sucesso.'
            );
        } catch (\Exception $e) {
            $code = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return $this->errorResponse($e->getMessage(), $code);
        }
    }

    /**
     * Update the status of a specific ticket via API
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

            return $this->successResponse(
                new TicketResource($ticket->load(['customer', 'assignee', 'messages.sender'])),
                'Estado do ticket atualizado.'
            );
        } catch (\Exception $e) {
            $code = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return $this->errorResponse($e->getMessage(), $code);
        }
    }

    /**
     * Send a message and deduct time if necessary
     *
     * @param Request $request
     * @param Ticket $ticket
     * @return JsonResponse
     */
    public function sendMessage(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('update', $ticket);

        $validated = $request->validate([
            'message' => 'required_without:attachment|nullable|string',
            'attachment' => 'nullable|file|max:10240',
        ]);

        try {
            $this->ticketService->sendMessage(
                $request->user(),
                $ticket,
                $validated,
                $request->file('attachment')
            );

            return $this->successResponse(
                new TicketResource($ticket->load(['customer', 'assignee', 'messages.sender'])), 
                'Mensagem enviada com sucesso.'
            );
        } catch (\Exception $e) {
            $code = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return $this->errorResponse($e->getMessage(), $code);
        }
    }

    /**
     * API Heartbeat to deduct support time
     *
     * @param Request $request
     * @param Ticket $ticket
     * @param SupportTimeManager $timeManager
     * @return JsonResponse
     */
    public function tickTime(Request $request, Ticket $ticket, SupportTimeManager $timeManager): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSupporter()) {
            return $this->errorResponse('Não autorizado.', 403);
        }

        if ($ticket->assigned_to !== $user->id) {
            return $this->errorResponse('Precisas de reivindicar este ticket para descontar tempo.', 403);
        }

        Gate::authorize('update', $ticket);

        if ($ticket->status !== TicketStatusEnum::IN_PROGRESS) {
            return $this->errorResponse('Ticket não está em progresso.', 400);
        }

        $remainingSeconds = $timeManager->deductTime($ticket, 5);

        return $this->successResponse(
            ['remaining_seconds' => $remainingSeconds],
            'Tempo descontado com sucesso.'
        );
    }

    /**
     * Sync tags for a specific ticket via API
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

            return $this->successResponse(
                new TicketResource($ticket->load(['customer', 'assignee', 'messages.sender', 'tags'])),
                'Tags sincronizadas com sucesso.'
            );
        } catch (\Exception $e) {
            $code = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return $this->errorResponse($e->getMessage(), $code);
        }
    }
}