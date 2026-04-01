<?php

namespace App\Jobs;

use App\Services\EmailTicketService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Facades\Client;

/**
 * Isolates the heavy processing of single IMAP emails (including attachment streaming)
 * onto a background queue worker, preventing server RAM spikes during the cron execution.
 */
class ProcessIncomingEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Creates a new job instance.
     *
     * @param int $messageUid
     */
    public function __construct(
        public int $messageUid
    ) {}

    /**
     * Execute the job to fetch the specific message and stream attachments.
     */
    public function handle(EmailTicketService $ticketService): void
    {
        try {
            $client = Client::account('default');
            $client->connect();
            
            $folder = $client->getFolder('INBOX');
            $message = $folder->query()->getMessageByUid($this->messageUid);

            if ($message) {
                $ticketService->processEmailMessage($message);
                $message->setFlag('Seen');
            }

            $client->disconnect();
            
        } catch (\Exception $e) {
            Log::error("Queue failed to process IMAP Message UID {$this->messageUid}: " . $e->getMessage());
            $this->fail($e);
        }
    }
}