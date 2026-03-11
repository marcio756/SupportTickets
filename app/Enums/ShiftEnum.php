<?php

namespace App\Enums;

/**
 * Defines the available work shifts for teams.
 */
enum ShiftEnum: string
{
    case MORNING = 'morning';
    case AFTERNOON = 'afternoon';
    case NIGHT = 'night';
}