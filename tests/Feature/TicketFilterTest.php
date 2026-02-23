<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test if the tickets are correctly filtered by status.
     * Ensures pagination does not break the query scope.
     *
     * @return void
     */
    public function test_tickets_can_be_filtered_by_status()
    {
        $supporter = User::factory()->create(['role' => 'supporter']);
        
        Ticket::factory()->create(['status' => 'open']);
        Ticket::factory()->create(['status' => 'resolved']);

        $response = $this->actingAs($supporter)->get(route('tickets.index', ['status' => 'open']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Tickets/Index')
            ->where('tickets.data.0.status', 'open')
            ->has('tickets.data', 1)
        );
    }

    /**
     * Test if the tickets are correctly filtered by search term.
     *
     * @return void
     */
    public function test_tickets_can_be_filtered_by_search_term()
    {
        $supporter = User::factory()->create(['role' => 'supporter']);
        
        Ticket::factory()->create(['title' => 'Critical Server Error']);
        Ticket::factory()->create(['title' => 'Password reset issue']);

        $response = $this->actingAs($supporter)->get(route('tickets.index', ['search' => 'Server']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Tickets/Index')
            ->where('tickets.data.0.title', 'Critical Server Error')
            ->has('tickets.data', 1)
        );
    }
}