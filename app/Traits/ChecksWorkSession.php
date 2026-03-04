<?php

namespace App\Traits;

use App\Enums\WorkSessionStatusEnum;
use App\Models\WorkSession;

/**
 * Provides helper methods to verify the work session status of the authenticated user.
 * Keeps controllers DRY and focused.
 */
trait ChecksWorkSession
{
    /**
     * Retrieves the current active or paused session status for the user.
     *
     * @param \App\Models\User $user
     * @return string|null
     */
    protected function getWorkSessionStatus($user): ?string
    {
        if (!$user->isSupporter()) {
            return WorkSessionStatusEnum::ACTIVE->value; // Customers are implicitly active for their own data
        }

        $session = WorkSession::where('user_id', $user->id)
            ->whereIn('status', [WorkSessionStatusEnum::ACTIVE->value, WorkSessionStatusEnum::PAUSED->value])
            ->first();

        // As Laravel automatically casts the attribute to the Enum object, 
        // we must explicitly return its primitive string value to satisfy the return type.
        return $session ? $session->status->value : null;
    }
}