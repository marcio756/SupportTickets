<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\WorkSessionStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class TicketListWorkSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_supporter_without_active_session_sees_blocker_on_index()
    {
        /** @var \App\Models\User $supporter */
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);

        $response = $this->actingAs($supporter)->get(route('tickets.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Index')
            ->where('hasActiveSession', false)
        );
    }

    public function test_supporter_with_active_session_can_see_tickets_list()
    {
        /** @var \App\Models\User $supporter */
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        
        WorkSession::create([
            'user_id' => $supporter->id,
            'status' => WorkSessionStatusEnum::ACTIVE->value,
            'start_time' => now(),
        ]);

        $response = $this->actingAs($supporter)->get(route('tickets.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Index')
            ->where('hasActiveSession', true)
        );
    }

    public function test_supporter_without_active_session_cannot_view_ticket_details()
    {
        /** @var \App\Models\User $supporter */
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($supporter)->get(route('tickets.show', $ticket));

        // Deve ser redirecionado para a lista para ver o ecrã de bloqueio simpático
        $response->assertRedirect(route('tickets.index'));
        $response->assertSessionHas('error');
    }

    public function test_supporter_without_active_session_is_redirected_gracefully_on_write_action()
    {
        /** @var \App\Models\User $supporter */
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($supporter)->post(route('tickets.messages.store', $ticket), [
            'message' => 'Hello',
        ]);

        // Em vez de 403, devolve redirect com flash de erro
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}