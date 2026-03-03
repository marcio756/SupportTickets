<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Enums\RoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EmailIntegrationApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verifies if a supporter can manually trigger the IMAP email fetching process via API.
     *
     * @return void
     */
    public function test_supporter_can_trigger_email_fetch_via_api(): void
    {
        // Mock the Artisan command to prevent real IMAP connections during test execution
        Artisan::shouldReceive('call')
            ->with('app:fetch-support-emails')
            ->once()
            ->andReturn(0);

        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);

        $response = $this->actingAs($supporter, 'sanctum')->postJson(route('api.emails.fetch'));

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Emails fetched successfully.']);
    }

    /**
     * Verifies that regular customers cannot trigger the email sync.
     *
     * @return void
     */
    public function test_customer_cannot_trigger_email_fetch(): void
    {
        $customer = User::factory()->create(['role' => RoleEnum::CUSTOMER->value]);

        $response = $this->actingAs($customer, 'sanctum')->postJson(route('api.emails.fetch'));

        $response->assertStatus(403);
    }

    /**
     * Verifies if the API allows creating a ticket targeting an external email
     * without providing a registered customer_id.
     *
     * @return void
     */
    public function test_supporter_can_create_ticket_for_external_email(): void
    {
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);

        $payload = [
            'title' => 'External Email Ticket',
            'message' => 'This is a message to an external user',
            'sender_email' => 'external@example.com'
        ];

        $response = $this->actingAs($supporter, 'sanctum')->postJson(route('api.tickets.store'), $payload);

        $response->assertStatus(201)
                 ->assertJsonPath('data.sender_email', 'external@example.com')
                 ->assertJsonPath('data.source', 'email');
                 
        $this->assertDatabaseHas('tickets', [
            'sender_email' => 'external@example.com',
            'source' => 'email',
            'customer_id' => null,
        ]);
    }
}