<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that only supporters can view the tags management page.
     *
     * @return void
     */
    public function test_only_supporters_can_access_tags_index(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $supporter = User::factory()->create(['role' => 'supporter']);

        // Assert customer is blocked
        $this->actingAs($customer)->get(route('tags.index'))->assertStatus(403);

        // Assert supporter can view
        $this->actingAs($supporter)->get(route('tags.index'))->assertStatus(200);
    }

    /**
     * Verify that a customer cannot create a tag.
     *
     * @return void
     */
    public function test_customers_cannot_create_tags(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->post(route('tags.store'), [
            'name' => 'New Tag',
            'color' => '#ffffff',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('tags', ['name' => 'New Tag']);
    }
    
    /**
     * Verify that a supporter can create a new tag.
     *
     * @return void
     */
    public function test_supporters_can_create_tags(): void
    {
        $supporter = User::factory()->create(['role' => 'supporter']);

        $response = $this->actingAs($supporter)->post(route('tags.store'), [
            'name' => 'Server Issue',
            'color' => '#ff0000',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tags', ['name' => 'Server Issue']);
    }
}