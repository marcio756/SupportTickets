<?php

namespace Tests\Unit;

use App\Enums\RoleEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AttachmentService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TicketServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TicketService $ticketService;
    protected AttachmentService $attachmentServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->attachmentServiceMock = $this->createMock(AttachmentService::class);
        $this->ticketService = new TicketService($this->attachmentServiceMock);
    }

    public function test_customer_cannot_send_message_without_support_time()
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'daily_support_seconds' => 0,
        ]);

        $ticket = Ticket::create([
            'customer_id' => $customer->id,
            'title' => 'Test Ticket',
            'status' => TicketStatusEnum::IN_PROGRESS,
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('No support time available.');

        $this->ticketService->sendMessage($customer, $ticket, ['message' => 'Hello'], null);
    }

    public function test_customer_can_send_message_with_support_time()
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'daily_support_seconds' => 3600, // 1 Hour
        ]);

        $ticket = Ticket::create([
            'customer_id' => $customer->id,
            'title' => 'Test Ticket',
            'status' => TicketStatusEnum::IN_PROGRESS,
        ]);

        $message = $this->ticketService->sendMessage($customer, $ticket, ['message' => 'Valid Message'], null);

        $this->assertDatabaseHas('ticket_messages', [
            'id' => $message->id,
            'user_id' => $customer->id,
            'message' => 'Valid Message'
        ]);
    }

    /**
     * Test to ensure that a supporter with 5 active tickets is not automatically assigned.
     */
    public function test_supporter_is_not_assigned_more_than_five_active_tickets(): void
    {
        // Arrange
        $supporter = User::factory()->create(['role' => RoleEnum::SUPPORTER->value]);
        
        WorkSession::factory()->create([
            'user_id' => $supporter->id,
            'status' => WorkSessionStatusEnum::ACTIVE->value,
        ]);

        // Create 5 active tickets already assigned to this supporter
        Ticket::factory()->count(5)->create([
            'assigned_to' => $supporter->id,
            'status' => TicketStatusEnum::IN_PROGRESS->value,
        ]);

        $ticketService = app(\App\Services\TicketService::class);
        $customer = User::factory()->create(['role' => RoleEnum::CUSTOMER->value]);

        // Act
        $newTicket = $ticketService->createTicket($customer, [
            'title' => 'Test Ticket',
            'description' => 'Should not be assigned to the busy supporter',
            'source' => 'web'
        ]);

        // Assert
        $this->assertNull($newTicket->assigned_to, 'The ticket should remain unassigned because the supporter is at max capacity.');
    }
}