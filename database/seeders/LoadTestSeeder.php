<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Enums\TicketStatusEnum;
use App\Enums\WorkSessionStatusEnum;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

/**
 * Massive Load Test Seeder
 * Refactored for extreme performance to handle millions of records.
 * Uses safer chunk sizes to prevent SQLite "too many SQL variables" exceptions.
 */
class LoadTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting MASSIVE Load Test Seeder. This will generate millions of records...');

        $this->setupCoreEnvironment();

        // 1. Prepare fast static variables to avoid recalculation overhead in loops
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $password = Hash::make('123'); // Hashed once, used for all users
        DB::disableQueryLog(); 

        // 2. Configuration for Millions of Records
        $totalCustomers = 50000;
        $totalSupporters = 1000;
        $totalTickets = 1000000;       // 1 Million Tickets
        $totalMessages = 2000000;      // 2 Million Messages
        $totalWorkSessions = 500000;   // Half a Million Work Sessions
        $chunkSize = 500;              // Safe limit for SQLite (prevents "too many variables" crash)

        // --- A. CUSTOMERS ---
        $this->command->info("1. Generating {$totalCustomers} Customers...");
        for ($i = 0; $i < ($totalCustomers / $chunkSize); $i++) {
            $payload = [];
            for ($j = 0; $j < $chunkSize; $j++) {
                $payload[] = [
                    'name' => "Customer {$i}_{$j}",
                    'email' => "customer_{$i}_{$j}_" . uniqid() . "@example.com",
                    'password' => $password,
                    'role' => RoleEnum::CUSTOMER->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('users')->insert($payload);
            $this->command->info('   - ' . (($i + 1) * $chunkSize) . ' customers inserted.');
        }

        // --- B. SUPPORTERS ---
        $this->command->info("2. Generating {$totalSupporters} Supporters...");
        $teamIds = DB::table('teams')->pluck('id')->toArray();
        $payload = [];
        for ($j = 0; $j < $totalSupporters; $j++) {
            $payload[] = [
                'name' => "Supporter {$j}",
                'email' => "supporter_{$j}_" . uniqid() . "@example.com",
                'password' => $password,
                'role' => RoleEnum::SUPPORTER->value,
                'team_id' => $teamIds[array_rand($teamIds)],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('users')->insert($payload);

        // Fetch IDs for fast Foreign Key mapping
        $customerIds = DB::table('users')->where('role', RoleEnum::CUSTOMER->value)->pluck('id')->toArray();
        $supporterIds = DB::table('users')->where('role', RoleEnum::SUPPORTER->value)->pluck('id')->toArray();
        $statuses = [TicketStatusEnum::OPEN->value, TicketStatusEnum::IN_PROGRESS->value, TicketStatusEnum::RESOLVED->value, TicketStatusEnum::CLOSED->value];
        $sources = ['web', 'email'];

        // --- C. TICKETS ---
        $this->command->info("3. Generating {$totalTickets} Tickets (1 MILLION)...");
        for ($i = 0; $i < ($totalTickets / $chunkSize); $i++) {
            $payload = [];
            for ($j = 0; $j < $chunkSize; $j++) {
                $payload[] = [
                    'title' => "Massive Load Issue {$i}_{$j}",
                    'status' => $statuses[array_rand($statuses)],
                    'source' => $sources[array_rand($sources)],
                    'customer_id' => $customerIds[array_rand($customerIds)],
                    'assigned_to' => rand(0, 1) ? $supporterIds[array_rand($supporterIds)] : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('tickets')->insert($payload);
            
            // Only print every 50k to avoid terminal spam slowing down the process
            if ((($i + 1) * $chunkSize) % 50000 === 0) {
                $this->command->info('   - ' . (($i + 1) * $chunkSize) . ' tickets inserted.');
            }
        }

        // --- D. MESSAGES ---
        $this->command->info("4. Generating {$totalMessages} Ticket Messages (2 MILLION)...");
        $minTicketId = DB::table('tickets')->min('id');
        $maxTicketId = DB::table('tickets')->max('id');

        for ($i = 0; $i < ($totalMessages / $chunkSize); $i++) {
            $payload = [];
            for ($j = 0; $j < $chunkSize; $j++) {
                $payload[] = [
                    'ticket_id' => rand($minTicketId, $maxTicketId),
                    'user_id' => rand(0, 1) ? $customerIds[array_rand($customerIds)] : $supporterIds[array_rand($supporterIds)],
                    'message' => 'Stress test payload message. High volume simulation.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('ticket_messages')->insert($payload);
            
            if ((($i + 1) * $chunkSize) % 100000 === 0) {
                $this->command->info('   - ' . (($i + 1) * $chunkSize) . ' messages inserted.');
            }
        }

        // --- E. WORK SESSIONS ---
        $this->command->info("5. Generating {$totalWorkSessions} Work Sessions (500k)...");
        for ($i = 0; $i < ($totalWorkSessions / $chunkSize); $i++) {
            $payload = [];
            for ($j = 0; $j < $chunkSize; $j++) {
                $payload[] = [
                    'user_id' => $supporterIds[array_rand($supporterIds)],
                    'status' => WorkSessionStatusEnum::COMPLETED->value,
                    'started_at' => $now,
                    'ended_at' => $now,
                    'total_worked_seconds' => rand(3600, 28800), // Random hours between 1 and 8
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('work_sessions')->insert($payload);
            
            if ((($i + 1) * $chunkSize) % 50000 === 0) {
                $this->command->info('   - ' . (($i + 1) * $chunkSize) . ' work sessions inserted.');
            }
        }

        $this->command->info('EXTREME Load Test Database Seeding Completed Successfully!');
    }

    /**
     * Sets up the foundational data required for the application to function properly
     * before injecting the massive test payload.
     *
     * @return void
     */
    private function setupCoreEnvironment(): void
    {
        $this->command->info('   - Bootstrapping core environment...');
        Tag::firstOrCreate(['name' => 'Bug'], ['color' => '#ef4444']);
        $teamAlpha = Team::firstOrCreate(['name' => 'Alpha Support'], ['shift' => 'morning']);

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin Demo', 'password' => Hash::make('123'), 'role' => RoleEnum::ADMIN->value]
        );

        User::firstOrCreate(
            ['email' => 'support@example.com'],
            ['name' => 'Support Demo', 'password' => Hash::make('123'), 'role' => RoleEnum::SUPPORTER->value, 'team_id' => $teamAlpha->id]
        );

        User::firstOrCreate(
            ['email' => 'customer@example.com'],
            ['name' => 'Customer Demo', 'password' => Hash::make('123'), 'role' => RoleEnum::CUSTOMER->value]
        );
    }
}