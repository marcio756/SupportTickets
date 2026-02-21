<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetCustomerChatTime extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'support:reset-time';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resets the daily support time (1800 seconds) for all customer accounts.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Starting daily support time reset for customers...');

        // Bulk update to optimize database queries instead of iterating through each user model
        $updatedRows = User::where('role', 'customer')
            ->update(['daily_support_seconds' => 1800]);

        $this->info("Successfully reset support time for {$updatedRows} customers.");
    }
}