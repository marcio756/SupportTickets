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
     * Guarda o anúncio na BD e envia o job de emails em massa.
     */
    public function store(Request $request): JsonResponse
    {
        $role = $request->user()->getAttribute('role');
        $roleValue = $role instanceof RoleEnum ? $role->value : $role;

        if (!in_array($roleValue, [RoleEnum::ADMIN->value, RoleEnum::SUPPORTER->value])) {
            return $this->errorResponse('Acesso não autorizado a esta funcionalidade.', 403);
        }

        // Validação ajustada para o payload enviado pela App Flutter
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'type' => ['required', 'string', 'in:info,warning,critical'],
        ]);

        // 1. Guardar no histórico da base de dados
        $announcement = Announcement::create($validated);

        // 2. Disparar emails em massa apenas para perfis Customer (Otimizado por chunks)
        User::where('role', 'customer')
            ->select('id', 'email')
            ->chunk(500, function ($customers) use ($announcement) {
                foreach ($customers as $customer) {
                    SendAnnouncementEmailJob::dispatch(
                        $customer->email,
                        $announcement->title,
                        $announcement->content
                    );
                }
            });

        return $this->successResponse(
            $announcement, 
            __('announcements.sent_successfully') ?? 'Anúncio publicado e emails na fila de envio.'
        );
    }
}