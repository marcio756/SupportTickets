<?php

namespace App\Console\Commands;

use App\Jobs\ProcessIncomingEmailJob;
use Illuminate\Console\Command;
use Webklex\IMAP\Facades\Client;
use Illuminate\Support\Facades\Log;

/**
 * Console command that connects to the configured IMAP mailbox,
 * retrieves unread messages UIDs, and dispatches them to Queues to prevent Memory Spikes.
 */
class FetchSupportEmails extends Command
{
    protected $signature = 'app:fetch-support-emails';
    protected $description = 'Fetches unread emails UIDs from the IMAP server and queues them for processing.';

    public function handle(): int
    {
        $this->info('Starting to scan for unread support emails...');

        try {
            $client = Client::account('default');
            $client->connect();

            $folder = $client->getFolder('INBOX');
            // Architect Note: We fetch ONLY the minimal message headers to save RAM
            $messages = $folder->query()->unseen()->setFetchFlags(false)->get();

            $this->info('Found ' . $messages->count() . ' unread messages. Dispatching to workers...');

            foreach ($messages as $message) {
                try {
                    // Instantly push the UID to Redis/Queue without loading bodies or attachments
                    ProcessIncomingEmailJob::dispatch($message->getUid());
                    $this->info("Queued Message UID: {$message->getUid()}");
                } catch (\Exception $emailException) {
                    Log::error('Error queuing IMAP UID: ' . $emailException->getMessage());
                }
            }

            $client->disconnect();
            $this->info('Email dispatching routine completed.');

            return self::SUCCESS;

        } catch (\Exception $e) {
            Log::error('IMAP Mailbox Scan Error: ' . $e->getMessage());
            $this->error('Failed to scan incoming emails. Check application logs for details.');
            
            return self::FAILURE;
        }
    }
}