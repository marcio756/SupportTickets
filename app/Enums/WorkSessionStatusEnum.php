<?php

namespace App\Enums;

/**
 * Defines the strict state machine for supporter work sessions.
 */
enum WorkSessionStatusEnum: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
}