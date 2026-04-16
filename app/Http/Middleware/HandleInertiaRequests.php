<?php

namespace App\Http\Middleware;

use App\Enums\WorkSessionStatusEnum;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                // Partilha globalmente o estado do turno para que o Frontend (Vue) saiba a realidade da Base de Dados
                'work_session' => fn () => $this->getActiveWorkSession($request->user()),
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'status' => fn () => $request->session()->get('status'),
                // Adicionadas as variáveis de Flash Data exclusivas do 2FA
                'qr_code' => fn () => $request->session()->get('qr_code'),
                'secret' => fn () => $request->session()->get('secret'),
            ],
        ];
    }

    /**
     * Helper otimizado para carregar a sessão ativa do utilizador sem causar queries desnecessárias a clientes.
     */
    private function getActiveWorkSession($user): ?WorkSession
    {
        if (!$user || !($user->isSupporter() || $user->isAdmin())) {
            return null;
        }

        return WorkSession::with('pauses')
            ->where('user_id', $user->id)
            ->whereIn('status', [WorkSessionStatusEnum::ACTIVE->value, WorkSessionStatusEnum::PAUSED->value])
            ->first();
    }
}