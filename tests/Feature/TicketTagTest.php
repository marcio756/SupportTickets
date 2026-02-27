<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTagTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that a supporter can successfully assign tags to a ticket.
     *
     * @return void
     */
    public function test_supporter_can_sync_tags_to_ticket(): void
    {
        $supporter = User::factory()->create(['role' => 'supporter']);
        $ticket = Ticket::factory()->create();
        
        $tag1 = Tag::create(['name' => 'Urgent', 'color' => '#ff0000']);
        $tag2 = Tag::create(['name' => 'Bug', 'color' => '#000000']);

        $response = $this->actingAs($supporter)
            ->put(route('tickets.tags.sync', $ticket), [
                'tags' => [$tag1->id, $tag2->id]
            ]);

        $response->assertRedirect();
        $this->assertCount(2, $ticket->fresh()->tags);
        $this->assertTrue($ticket->fresh()->tags->contains($tag1->id));
    }

    /**
     * Verify that ticket index query can be filtered by specific tag IDs.
     *
     * @return void
     */
    public function test_tickets_can_be_filtered_by_tags(): void
    {
        $supporter = User::factory()->create(['role' => 'supporter']);
        
        $tag = Tag::create(['name' => 'Frontend', 'color' => '#blue']);
        
        $ticketWithTag = Ticket::factory()->create();
        $ticketWithTag->tags()->attach($tag->id);
        
        $ticketWithoutTag = Ticket::factory()->create();

        $response = $this->actingAs($supporter)
            ->get(route('tickets.index', ['tags' => [$tag->id]]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Tickets/Index')
            ->has('tickets.data', 1)
        );
    }
}