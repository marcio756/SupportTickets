<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $activeSessionData = null;

        try {
            if ($request->user() && $request->user()->isSupporter()) {
                $session = \App\Models\WorkSession::where('user_id', $request->user()->id)
                    ->whereIn('status', [\App\Enums\WorkSessionStatusEnum::ACTIVE->value, \App\Enums\WorkSessionStatusEnum::PAUSED->value])
                    ->first();
                
                if ($session) {
                    // Arquitetura: Passar um array simples impede que o Inertia tente serializar
                    // Modelos Eloquent completos, cortando a raiz do Memory Leak e do Erro 500.
                    $activeSessionData = [
                        'id' => $session->id,
                        'status' => $session->status instanceof \BackedEnum ? $session->status->value : $session->status,
                        'total_duration_seconds' => $session->total_duration_seconds,
                        'started_at' => $session->started_at,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Silencia qualquer falha durante a verificação isolando o logger
            try { logger()->error('Erro no HandleInertiaRequests: ' . $e->getMessage()); } catch (\Throwable $log) {}
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'work_session' => $activeSessionData, 
            ],
            'locale' => app()->getLocale(),
        ];
    }
}