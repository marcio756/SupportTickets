<?php

namespace App\Console\Commands;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Architect Note: Refactored to use Chunking. 
 * Executing a single UPDATE statement on millions of records causes table locking 
 * (blocking new registrations or logins) and transaction log exhaustion.
 */
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

        $totalUpdated = 0;

        // Process records in batches of 5000 to maintain database responsiveness
        User::where('role', RoleEnum::CUSTOMER->value)
            ->chunkById(5000, function ($users) use (&$totalUpdated) {
                
                $updated = User::whereIn('id', $users->pluck('id'))
                    ->update(['daily_support_seconds' => 1800]);
                
                $totalUpdated += $updated;
                
                $this->info("Processed {$totalUpdated} records...");
            });

        $this->info("Successfully reset support time for {$totalUpdated} customers.");
    }
}