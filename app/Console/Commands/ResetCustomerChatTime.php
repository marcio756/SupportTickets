<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Enums\RoleEnum;
use Illuminate\Console\Command;

class ResetCustomerChatTime extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:reset-time';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resets the daily support chat time for all customers to 30 minutes.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to reset customer support times...');

        // 1800 seconds = 30 minutes
        $updatedRows = User::where('role', RoleEnum::CUSTOMER->value)
            ->update(['daily_support_seconds' => 1800]);

        $this->info("Successfully reset time for {$updatedRows} customers.");
    }
}