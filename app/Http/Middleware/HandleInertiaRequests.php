<?php

namespace App\Http\Middleware;

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
    public function version(Request $request): ?string
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
        // Check if there is an active work session for the supporter
        $activeSession = null;
        if ($request->user() && $request->user()->isSupporter()) {
            $activeSession = \App\Models\WorkSession::where('user_id', $request->user()->id)
                ->whereIn('status', [\App\Enums\WorkSessionStatusEnum::ACTIVE->value, \App\Enums\WorkSessionStatusEnum::PAUSED->value])
                ->first();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'work_session' => $activeSession, 
            ],
            // Shares the current backend locale state to initialize the frontend translation
            'locale' => app()->getLocale(),
        ];
    }
}