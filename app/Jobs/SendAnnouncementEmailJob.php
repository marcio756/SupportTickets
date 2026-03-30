<?php

namespace App\Jobs;

use App\Mail\AnnouncementMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Background job to send an announcement email to a specific customer asynchronously.
 */
class SendAnnouncementEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $recipientEmail;
    public string $subjectLine;
    public string $htmlContent;

    /**
     * Create a new job instance.
     *
     * @param string $recipientEmail Target customer email address.
     * @param string $subjectLine Announcement subject.
     * @param string $htmlContent Announcement rich text content.
     */
    public function __construct(string $recipientEmail, string $subjectLine, string $htmlContent)
    {
        $this->recipientEmail = $recipientEmail;
        $this->subjectLine = $subjectLine;
        $this->htmlContent = $htmlContent;
    }

    /**
     * Execute the job.
     * Sends the email using Laravel's Mail facade.
     */
    public function handle(): void
    {
        Mail::to($this->recipientEmail)->send(
            new AnnouncementMail($this->subjectLine, $this->htmlContent)
        );
    }
}