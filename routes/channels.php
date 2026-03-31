<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Ticket;
use App\Enums\RoleEnum;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Architect Note: Otimização da autorização do Websocket para permitir Staff
// e impedir bloqueios 403 indevidos quando a página do Ticket carrega.
Broadcast::channel('ticket.{ticketId}', function ($user, $ticketId) {
    $role = $user->getAttribute('role');
    $roleValue = $role instanceof RoleEnum ? $role->value : $role;

    if (in_array($roleValue, [RoleEnum::ADMIN->value, RoleEnum::SUPPORTER->value])) {
        return true;
    }

    $ticket = Ticket::find($ticketId);
    return $ticket && (int) $ticket->customer_id === (int) $user->id;
});