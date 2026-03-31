<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Enums\ShiftEnum;
use App\Enums\TicketStatusEnum;
use App\Enums\WorkSessionStatusEnum;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

/**
 * Massive Load Test Seeder
 * Refactored by Architecture for EXTREME performance to handle millions of records.
 * Uses pre-calculated array pools and safe chunk sizes to prevent Memory Leaks and DB crashes.
 */
class LoadTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting MASSIVE Load Test Seeder. This will generate ~4.1 MILLION records...');

        $this->setupCoreEnvironment();

        // 1. Prepare fast static variables to avoid recalculation overhead in loops
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $password = Hash::make('123'); // Hashed once, used for all users
        DB::disableQueryLog(); 

        // 2. Configuration for Millions of Records
        $totalTeams = 100;
        $totalTags = 500;
        $totalCustomers = 100000;      // 100k
        $totalSupporters = 2000;       // 2k
        $totalTickets = 1000000;       // 1 Million
        $totalMessages = 2000000;      // 2 Million
        $totalWorkSessions = 1000000;  // 1 Million
        $totalTicketTags = 1000000;    // 1 Million Pivot Relations
        $chunkSize = 1000;             // Safe and fast limit for Batch Inserts
        
        // 3. Pre-generate Pools for randomized realistic dates (Bypasses heavy Carbon CPU usage in loops)
        $this->command->info('0. Pre-calculating realistic random date pools to maximize performance...');
        $sessionDatesPool = [];
        
        for($i = 0; $i < 5000; $i++) {
            $startWS = Carbon::now()->subDays(rand(0, 730))->subMinutes(rand(0, 1440)); // Spread over 2 years
            $endWS = $startWS->copy()->addSeconds(rand(1800, 28800)); // Work duration: 30m to 8h
            $sessionDatesPool[] = [
                'started_at' => $startWS->format('Y-m-d H:i:s'),
                'ended_at' => $endWS->format('Y-m-d H:i:s'),
                'total_worked_seconds' => $startWS->diffInSeconds($endWS)
            ];
        }

        // --- A. TEAMS & TAGS ---
        $this->command->info("1. Generating {$totalTeams} Teams and {$totalTags} Tags...");
        $shifts = [ShiftEnum::MORNING->value ?? 'morning', ShiftEnum::AFTERNOON->value ?? 'afternoon', ShiftEnum::NIGHT->value ?? 'night'];
        
        $teamsPayload = [];
        for ($i = 0; $i < $totalTeams; $i++) {
            $teamsPayload[] = ['name' => "Team Load {$i}", 'shift' => $shifts[array_rand($shifts)], 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('teams')->insert($teamsPayload);

        $tagsPayload = [];
        for ($i = 0; $i < $totalTags; $i++) {
            $tagsPayload[] = ['name' => "Tag {$i}", 'color' => sprintf('#%06X', mt_rand(0, 0xFFFFFF)), 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($tagsPayload, $chunkSize) as $chunk) DB::table('tags')->insert($chunk);

        $teamIds = DB::table('teams')->pluck('id')->toArray();
        $tagIds = DB::table('tags')->pluck('id')->toArray();

        // --- B. CUSTOMERS ---
        $this->command->info("2. Generating {$totalCustomers} Customers...");
        for ($i = 0; $i < ($totalCustomers / $chunkSize); $i++) {
            $payload = [];
            for ($j = 0; $j < $chunkSize; $j++) {
                $payload[] = [
                    'name' => "Customer {$i}_{$j}",
                    'email' => "customer_{$i}_{$j}_" . uniqid() . "@example.com",
                    'password' => $password,
                    'role' => RoleEnum::CUSTOMER->value ?? 'customer',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('users')->insert($payload);
            if ((($i + 1) * $chunkSize) % 25000 === 0) $this->command->info('   - ' . (($i + 1) * $chunkSize) . ' customers inserted.');
        }

        // --- C. SUPPORTERS ---
        $this->command->info("3. Generating {$totalSupporters} Supporters...");
        $payload = [];
        for ($j = 0; $j < $totalSupporters; $j++) {
            $payload[] = [
                'name' => "Supporter {$j}",
                'email' => "supporter_{$j}_" . uniqid() . "@example.com",
                'password' => $password,
                'role' => RoleEnum::SUPPORTER->value ?? 'supporter',
                'team_id' => $teamIds[array_rand($teamIds)],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($payload, $chunkSize) as $chunk) DB::table('users')->insert($chunk);

        // Fetch IDs for fast Foreign Key mapping
        $customerIds = DB::table('users')->where('role', RoleEnum::CUSTOMER->value ?? 'customer')->pluck('id')->toArray();
        $supporterIds = DB::table('users')->whereIn('role', [RoleEnum::SUPPORTER->value ?? 'supporter', RoleEnum::ADMIN->value ?? 'admin'])->pluck('id')->toArray();
        $statuses = [TicketStatusEnum::OPEN->value ?? 'open', TicketStatusEnum::IN_PROGRESS->value ?? 'in_progress', TicketStatusEnum::RESOLVED->value ?? 'resolved', TicketStatusEnum::CLOSED->value ?? 'closed'];
        $sources = ['web', 'email'];

        // --- D. TICKETS ---
        $this->command->info("4. Generating {$totalTickets} Tickets (1 MILLION)...");
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
            if ((($i + 1) * $chunkSize) % 100000 === 0) $this->command->info('   - ' . (($i + 1) * $chunkSize) . ' tickets inserted.');
        }

        // --- E. MESSAGES ---
        $this->command->info("5. Generating {$totalMessages} Ticket Messages (2 MILLION)...");
        $minTicketId = DB::table('tickets')->min('id');
        $maxTicketId = DB::table('tickets')->max('id');

        for ($i = 0; $i < ($totalMessages / $chunkSize); $i++) {
            $payload = [];
            for ($j = 0; $j < $chunkSize; $j++) {
                $payload[] = [
                    'ticket_id' => rand($minTicketId, $maxTicketId),
                    'user_id' => rand(0, 1) ? $customerIds[array_rand($customerIds)] : $supporterIds[array_rand($supporterIds)],
                    'message' => 'Stress test payload message. High volume simulation to check rendering times and database indexes.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('ticket_messages')->insert($payload);
            if ((($i + 1) * $chunkSize) % 250000 === 0) $this->command->info('   - ' . (($i + 1) * $chunkSize) . ' messages inserted.');
        }

        // --- F. WORK SESSIONS ---
        $this->command->info("6. Generating {$totalWorkSessions} Work Sessions (1 MILLION) with realistic timestamps...");
        for ($i = 0; $i < ($totalWorkSessions / $chunkSize); $i++) {
            $payload = [];
            for ($j = 0; $j < $chunkSize; $j++) {
                $poolPick = $sessionDatesPool[array_rand($sessionDatesPool)];
                $payload[] = [
                    'user_id' => $supporterIds[array_rand($supporterIds)],
                    'status' => WorkSessionStatusEnum::COMPLETED->value ?? 'completed',
                    'started_at' => $poolPick['started_at'],
                    'ended_at' => $poolPick['ended_at'],
                    'total_worked_seconds' => $poolPick['total_worked_seconds'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('work_sessions')->insert($payload);
            if ((($i + 1) * $chunkSize) % 100000 === 0) $this->command->info('   - ' . (($i + 1) * $chunkSize) . ' work sessions inserted.');
        }

        // --- G. TICKET TAGS PIVOT ---
        $pivotTable = null;
        if (Schema::hasTable('tag_ticket')) $pivotTable = 'tag_ticket';
        elseif (Schema::hasTable('ticket_tag')) $pivotTable = 'ticket_tag';

        if ($pivotTable) {
            $this->command->info("7. Generating {$totalTicketTags} Tag assignments for Tickets...");
            for ($i = 0; $i < ($totalTicketTags / $chunkSize); $i++) {
                $payload = [];
                for ($j = 0; $j < $chunkSize; $j++) {
                    $payload[] = [
                        'ticket_id' => rand($minTicketId, $maxTicketId),
                        'tag_id' => $tagIds[array_rand($tagIds)],
                    ];
                }
                
                try {
                    DB::table($pivotTable)->insertOrIgnore($payload);
                } catch (\Exception $e) {
                    DB::table($pivotTable)->insert($payload);
                }
                
                if ((($i + 1) * $chunkSize) % 250000 === 0) $this->command->info('   - ' . (($i + 1) * $chunkSize) . ' tag relations inserted.');
            }
        }

        $this->command->info('EXTREME Load Test Database Seeding Completed Successfully! The system is now heavily populated.');
    }

    private function setupCoreEnvironment(): void
    {
        $this->command->info('   - Bootstrapping core environment (Keeping standard test users)...');
        Tag::firstOrCreate(['name' => 'Bug'], ['color' => '#ef4444']);
        $teamAlpha = Team::firstOrCreate(['name' => 'Alpha Support'], ['shift' => ShiftEnum::MORNING->value ?? 'morning']);

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin Demo', 'password' => Hash::make('123'), 'role' => RoleEnum::ADMIN->value ?? 'admin']
        );

        User::firstOrCreate(
            ['email' => 'support@example.com'],
            ['name' => 'Support Demo', 'password' => Hash::make('123'), 'role' => RoleEnum::SUPPORTER->value ?? 'supporter', 'team_id' => $teamAlpha->id]
        );

        User::firstOrCreate(
            ['email' => 'customer@example.com'],
            ['name' => 'Customer Demo', 'password' => Hash::make('123'), 'role' => RoleEnum::CUSTOMER->value ?? 'customer']
        );
    }
}