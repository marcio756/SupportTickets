<?php

namespace App\Enums;

/**
 * Defines the available user roles within the system.
 */
enum RoleEnum: string
{
    case ADMIN = 'admin';
    case CUSTOMER = 'customer';
    case SUPPORTER = 'supporter';
}