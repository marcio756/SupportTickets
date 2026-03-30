<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable class for formatting customer announcements.
 */
class AnnouncementMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectLine;
    public string $htmlContent;

    /**
     * Create a new message instance.
     *
     * @param string $subjectLine The subject of the announcement.
     * @param string $htmlContent The rich HTML content of the announcement.
     */
    public function __construct(string $subjectLine, string $htmlContent)
    {
        $this->subjectLine = $subjectLine;
        $this->htmlContent = $htmlContent;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlContent,
        );
    }
}