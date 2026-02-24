<?php

namespace App\Http\Controllers\Api;

use App\Enums\TicketStatusEnum;
use App\Events\TicketMessageCreated;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\AttachmentService;
use App\Services\SupportTimeManager;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Full API Controller for managing Support Tickets
 */
class TicketController extends Controller
{
    use ApiResponser;

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
     * Store a newly created ticket and its initial message via API
     */
    public function store(Request $request, AttachmentService $attachmentService): JsonResponse
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

        $attachmentPath = $request->hasFile('attachment') 
            ? $attachmentService->store($request->file('attachment')) 
            : null;

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'attachment_path' => $attachmentPath,
        ]);

        return $this->successResponse(
            new TicketResource($ticket->load(['customer', 'assignee', 'messages.sender'])), 
            'Ticket criado com sucesso.', 
            201
        );
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
     * Assigns the ticket to the authenticated supporter via API
     */
    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        if (! $request->user()->isSupporter()) {
            return $this->errorResponse('Apenas equipa de suporte pode reivindicar tickets.', 403);
        }

        $ticket->update([
            'assigned_to' => $request->user()->id,
        ]);

        return $this->successResponse(
            new TicketResource($ticket->load(['customer', 'assignee'])),
            'Ticket reivindicado com sucesso.'
        );
    }

    /**
     * Update the status of a specific ticket via API
     */
    public function updateStatus(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        if ($user->isSupporter() && $ticket->assigned_to !== $user->id) {
            return $this->errorResponse('Precisas de estar atribuído a este ticket para mudar o estado.', 403);
        } elseif (! $user->isSupporter() && $ticket->customer_id !== $user->id) {
            return $this->errorResponse('Acesso não autorizado.', 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::enum(TicketStatusEnum::class)],
        ]);

        $newStatus = $validated['status'];

        if (! $user->isSupporter() && $newStatus !== TicketStatusEnum::RESOLVED->value) {
            return $this->errorResponse('Clientes apenas podem marcar tickets como resolvidos.', 403);
        }

        $ticket->update(['status' => $newStatus]);

        $message = $ticket->messages()->create([
            'user_id' => $user->id,
            'message' => "🔄 O estado do ticket foi alterado para: " . strtoupper($newStatus),
        ]);

        $message->load('sender');
        broadcast(new TicketMessageCreated($message));

        return $this->successResponse(
            new TicketResource($ticket->load(['customer', 'assignee', 'messages.sender'])),
            'Estado do ticket atualizado.'
        );
    }

    /**
     * Send a message and deduct time if necessary
     */
    public function sendMessage(Request $request, Ticket $ticket, AttachmentService $attachmentService): JsonResponse
    {
        Gate::authorize('update', $ticket);

        if ($ticket->status !== TicketStatusEnum::IN_PROGRESS) {
            return $this->errorResponse('O ticket precisa de estar Em Progresso para enviar mensagens.', 403);
        }

        $request->validate([
            'message' => 'required|string',
            'attachment' => 'nullable|file|max:10240',
        ]);

        $user = $request->user();

        if ($user->isCustomer() && $user->daily_support_seconds <= 0) {
            return $this->errorResponse('Sem tempo de suporte disponível.', 403);
        }

        $attachmentPath = $request->hasFile('attachment') 
            ? $attachmentService->store($request->file('attachment')) 
            : null;

        $message = $ticket->messages()->create([
            'user_id' => $user->id,
            'message' => $request->message,
            'attachment_path' => $attachmentPath,
        ]);

        $message->load('sender');
        broadcast(new TicketMessageCreated($message));

        return $this->successResponse(
            new TicketResource($ticket->load(['customer', 'assignee', 'messages.sender'])), 
            'Mensagem enviada com sucesso.'
        );
    }

    /**
     * API Heartbeat to deduct support time
     */
    public function tickTime(Ticket $ticket, SupportTimeManager $timeManager): JsonResponse
    {
        if (!auth()->user()->isSupporter()) {
            return $this->errorResponse('Não autorizado.', 403);
        }

        Gate::authorize('update', $ticket);

        if ($ticket->status !== TicketStatusEnum::IN_PROGRESS) {
            return $this->successResponse(['status' => 'not_in_progress'], 'Ticket não está em progresso.');
        }

        $remainingSeconds = $timeManager->deductTime($ticket, 5);

        return $this->successResponse(
            ['remaining_seconds' => $remainingSeconds],
            'Tempo descontado com sucesso.'
        );
    }
}