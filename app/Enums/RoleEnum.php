<?php

namespace App\Enums;

/**
 * Defines the available user roles within the system.
 */
enum RoleEnum: string
{
    case CUSTOMER = 'customer';
    case SUPPORTER = 'supporter';
    case ADMIN = 'admin';
}