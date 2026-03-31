<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\IMAP\Facades\Client;
use App\Services\EmailTicketService;
use Illuminate\Support\Facades\Log;

/**
 * Console command that connects to the configured IMAP mailbox,
 * retrieves unread messages, and delegates them to the ticketing service.
 */
class FetchSupportEmails extends Command
{
    protected $signature = 'app:fetch-support-emails';
    protected $description = 'Fetches unread emails from the IMAP server and processes them into support tickets.';

    public function handle(EmailTicketService $ticketService): int
    {
        $this->info('Starting to fetch support emails...');

        try {
            $client = Client::account('default');
            $client->connect();

            $folder = $client->getFolder('INBOX');
            $messages = $folder->query()->unseen()->get();

            $this->info('Found ' . $messages->count() . ' unread messages.');

            foreach ($messages as $message) {
                // Architect Note: Wrapped single email processing in a try/catch block.
                // If one badly formatted email throws an exception, it prevents the entire
                // command from crashing and successfully processes the remaining queue.
                try {
                    $body = $message->getTextBody() ?? $message->getHTMLBody() ?? '';
                    
                    $inReplyTo = $message->getInReplyTo();
                    $references = $message->getReferences();
                    
                    $emailData = [
                        'subject'     => (string) ($message->getSubject()[0] ?? 'No Subject'),
                        'body'        => is_string($body) ? trim($body) : '',
                        'from_email'  => $message->getFrom()[0]->mail ?? null,
                        'in_reply_to' => is_iterable($inReplyTo) ? implode(' ', $inReplyTo->toArray()) : (string) $inReplyTo,
                        'references'  => is_iterable($references) ? implode(' ', $references->toArray()) : (string) $references,
                    ];

                    if ($emailData['from_email']) {
                        $ticketService->processEmail($emailData);
                        
                        $message->setFlag('Seen');
                        
                        $this->info("Successfully processed email from: {$emailData['from_email']}");
                    }
                } catch (\Exception $emailException) {
                    Log::error('Error processing single IMAP message: ' . $emailException->getMessage());
                    $this->error('Failed on an email, check logs. Continuing to next message.');
                }
            }

            $client->disconnect();
            $this->info('Email fetching routine completed.');

            return self::SUCCESS;

        } catch (\Exception $e) {
            Log::error('IMAP Mailbox Fetch Error: ' . $e->getMessage());
            $this->error('Failed to process incoming emails. Check application logs for details.');
            
            return self::FAILURE;
        }
    }
}