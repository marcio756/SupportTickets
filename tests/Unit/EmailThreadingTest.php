<?php

namespace Tests\Unit;

use App\Mail\TicketCreatedAutoReply;
use App\Models\Ticket;
use App\Notifications\TicketNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Symfony\Component\Mime\Email;

class EmailThreadingTest extends TestCase
{
    /**
     * Verifies if the auto-reply mailable generates the correct deterministic Message-ID.
     *
     * @return void
     */
    public function test_ticket_auto_reply_has_correct_message_id_header(): void
    {
        Config::set('app.url', 'http://support.example.com');

        $ticket = new Ticket(['id' => 123]);
        $mailable = new TicketCreatedAutoReply($ticket);

        $headers = $mailable->headers();

        $this->assertEquals('ticket-123@support.example.com', $headers->messageId);
    }

    /**
     * Verifies if the notification includes standard RFC threading headers (In-Reply-To and References).
     *
     * @return void
     */
    public function test_ticket_notification_has_threading_headers(): void
    {
        Config::set('app.url', 'http://support.example.com');

        $ticket = new Ticket(['id' => 123]);
        $notification = new TicketNotification($ticket, 'Test reply message');

        /** @var MailMessage $mailMessage */
        $mailMessage = $notification->toMail((object)[]);

        // Create a dummy Symfony email to apply the callbacks and test the headers injection
        $symfonyMessage = new Email();

        foreach ($mailMessage->callbacks as $callback) {
            $callback($symfonyMessage);
        }

        $headers = $symfonyMessage->getHeaders();

        $this->assertTrue($headers->has('In-Reply-To'));
        $this->assertEquals('<ticket-123@support.example.com>', $headers->get('In-Reply-To')->getBodyAsString());

        $this->assertTrue($headers->has('References'));
        $this->assertEquals('<ticket-123@support.example.com>', $headers->get('References')->getBodyAsString());
    }
}