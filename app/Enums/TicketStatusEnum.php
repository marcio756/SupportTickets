<?php

namespace App\Enums;

/**
 * Defines the lifecycle states of a support ticket.
 */
enum TicketStatusEnum: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';
}