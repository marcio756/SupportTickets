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
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-support-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetches unread emails from the IMAP server and processes them into support tickets.';

    /**
     * Executes the console command.
     * Orchestrates the IMAP connection, mailbox polling, and ticket creation flow.
     *
     * @param \App\Services\EmailTicketService $ticketService
     * @return int
     */
    public function handle(EmailTicketService $ticketService): int
    {
        $this->info('Starting to fetch support emails...');

        try {
            // Retrieves the default account configured in config/imap.php
            $client = Client::account('default');
            $client->connect();

            // Accesses the primary inbox folder
            $folder = $client->getFolder('INBOX');
            
            // Filters only messages that haven't been read yet
            $messages = $folder->query()->unseen()->get();

            $this->info('Found ' . $messages->count() . ' unread messages.');

            foreach ($messages as $message) {
                // Extracts plain text body; falls back to HTML if plain text is unavailable
                $body = $message->getTextBody() ?? $message->getHTMLBody() ?? '';
                
                $emailData = [
                    'subject'    => $message->getSubject()[0] ?? 'No Subject',
                    'body'       => trim($body),
                    'from_email' => $message->getFrom()[0]->mail ?? null,
                ];

                if ($emailData['from_email']) {
                    $ticketService->processEmail($emailData);
                    
                    // Flags the message as 'Seen' on the mail server to prevent duplicate processing
                    $message->setFlag('Seen');
                    
                    $this->info("Successfully processed email from: {$emailData['from_email']}");
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