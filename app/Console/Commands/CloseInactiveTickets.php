<?php

namespace App\Console\Commands;

use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Notifications\TicketNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CloseInactiveTickets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:close-inactive-tickets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically close tickets waiting for customer reply for more than 72 hours';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $cutoffDate = Carbon::now()->subHours(72);

        /**
         * Fetch tickets that are currently active and load their latest message
         * to evaluate the interaction state.
         */
        $tickets = Ticket::with(['messages' => function ($query) {
            $query->latest();
        }])
        ->whereIn('status', [TicketStatusEnum::OPEN, TicketStatusEnum::IN_PROGRESS])
        ->get();

        $closedCount = 0;

        foreach ($tickets as $ticket) {
            $lastMessage = $ticket->messages->first();
            $lastActivityDate = $lastMessage ? $lastMessage->created_at : $ticket->created_at;

            if ($lastActivityDate < $cutoffDate) {
                
                /**
                 * Architectural Rule: Only close if the last message is NOT from the customer.
                 * If the customer was the last one to reply, the support team is delaying the answer,
                 * therefore we must not penalize the customer by closing their ticket.
                 */
                $isWaitingOnCustomer = $lastMessage && $lastMessage->user_id !== $ticket->customer_id;
                
                if ($isWaitingOnCustomer) {
                    $ticket->update(['status' => TicketStatusEnum::CLOSED]);

                    $notificationContent = 'O seu ticket foi fechado automaticamente devido a inatividade superior a 72 horas.';
                    
                    $ticket->customer->notify(new TicketNotification(
                        $ticket, 
                        $notificationContent, 
                        'ticket_closed_auto'
                    ));
                    
                    $closedCount++;
                }
            }
        }

        $this->info("Successfully closed {$closedCount} inactive tickets.");

        return self::SUCCESS;
    }
}