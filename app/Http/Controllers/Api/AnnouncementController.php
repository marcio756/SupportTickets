<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Jobs\SendAnnouncementEmailJob;
use App\Models\User;
use App\Models\Announcement;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * API Controller for dispatching and managing system announcements.
 */
class AnnouncementController extends Controller
{
    use ApiResponser;

    /**
     * Retorna o histórico de anúncios globais ordenado de forma decrescente.
     */
    public function index(Request $request): JsonResponse
    {
        $announcements = Announcement::latest()->paginate(15);
        return $this->successResponse($announcements);
    }

    /**
     * Guarda o anúncio na BD e envia o job de emails.
     */
    public function store(Request $request): JsonResponse
    {
        $role = $request->user()->getAttribute('role');
        $roleValue = $role instanceof RoleEnum ? $role->value : $role;

        if (!in_array($roleValue, [RoleEnum::ADMIN->value, RoleEnum::SUPPORTER->value])) {
            return $this->errorResponse('Acesso não autorizado a esta funcionalidade.', 403);
        }

        // Validação ajustada para o novo payload de envio de emails
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'target_audience' => ['required', 'string', 'in:all_customers,specific_customers'],
            'customer_ids' => ['required_if:target_audience,specific_customers', 'array'],
            'customer_ids.*' => ['integer', 'exists:users,id'],
        ]);

        // 1. Guardar no histórico da base de dados
        $announcement = Announcement::create([
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'target_audience' => $validated['target_audience'],
            'recipient_ids' => $validated['target_audience'] === 'specific_customers' ? $validated['customer_ids'] : null,
        ]);

        // 2. Preparar a Query de destinatários (Customers)
        $query = User::where('role', 'customer')->select('id', 'email');
        
        if ($announcement->target_audience === 'specific_customers' && !empty($announcement->recipient_ids)) {
            $query->whereIn('id', $announcement->recipient_ids);
        }

        // 3. Disparar emails em massa otimizado por chunks
        $query->chunk(500, function ($customers) use ($announcement) {
            foreach ($customers as $customer) {
                SendAnnouncementEmailJob::dispatch(
                    $customer->email,
                    $announcement->subject,
                    $announcement->message
                );
            }
        });

        return $this->successResponse(
            $announcement, 
            __('announcements.sent_successfully') ?? 'Anúncio publicado e emails na fila de envio.'
        );
    }
}