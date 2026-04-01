<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Jobs\SendAnnouncementEmailJob;
use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * API Controller for dispatching system announcements.
 * Optimized for bulk email dispatching without exhausting server RAM.
 */
class AnnouncementController extends Controller
{
    use ApiResponser;

    /**
     * Validates input and dispatches background jobs to send the announcements via API.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $role = $request->user()->getAttribute('role');
        $roleValue = $role instanceof RoleEnum ? $role->value : $role;

        if (!in_array($roleValue, [RoleEnum::ADMIN->value, RoleEnum::SUPPORTER->value])) {
            return $this->errorResponse('Acesso não autorizado a esta funcionalidade.', 403);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'customer_ids' => ['required', 'array', 'min:1'],
            'customer_ids.*' => ['exists:users,id'],
        ]);

        /**
         * Architect Note: Se o array de customer_ids tiver 50.000 IDs, usar get() 
         * para carregar todos os modelos User faria o servidor dar "Out of Memory".
         * Usamos chunk() e selecionamos apenas o email para manter o uso de RAM quase nulo 
         * enquanto despachamos os Jobs para o Redis/Queue.
         */
        User::whereIn('id', $validated['customer_ids'])
            ->select('id', 'email')
            ->chunk(500, function ($customers) use ($validated) {
                foreach ($customers as $customer) {
                    SendAnnouncementEmailJob::dispatch(
                        $customer->email,
                        $validated['subject'],
                        $validated['content']
                    );
                }
            });

        return $this->successResponse(
            null, 
            __('announcements.sent_successfully') ?? 'Anúncios colocados na fila de envio com sucesso.'
        );
    }
}