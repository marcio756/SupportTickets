<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Enums\WorkSessionStatusEnum;
use App\Models\Tag;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\Vacation;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with comprehensive test data.
     */
    public function run(): void
    {
        /**
         * 1. Create system default tags
         */
        $tagsData = [
            ['name' => 'Bug', 'color' => '#ef4444'],
            ['name' => 'Feature', 'color' => '#3b82f6'],
            ['name' => 'Question', 'color' => '#10b981'],
            ['name' => 'Urgent', 'color' => '#f59e0b'],
            ['name' => 'Billing', 'color' => '#8b5cf6'],
            ['name' => 'Suggestion', 'color' => '#ec4899'],
            ['name' => 'UI/UX', 'color' => '#14b8a6'],
            ['name' => 'Backend', 'color' => '#6366f1'],
            ['name' => 'Frontend', 'color' => '#f43f5e'],
            ['name' => 'Documentation', 'color' => '#64748b'],
        ];

        $createdTags = collect();
        foreach ($tagsData as $tag) {
            $createdTags->push(Tag::firstOrCreate(
                ['name' => $tag['name']],
                ['color' => $tag['color']]
            ));
        }

        /**
         * 2. Create Teams
         */
        $teams = collect([
            Team::firstOrCreate(['name' => 'Alpha Support'], ['shift' => 'morning']),
            Team::firstOrCreate(['name' => 'Beta Tech'], ['shift' => 'afternoon']),
            Team::firstOrCreate(['name' => 'Gamma Ops'], ['shift' => 'night']),
        ]);

        /**
         * 3. Create Core Demo Users
         */
        $testAdmin = User::factory()->create([
            'name' => 'Admin Demo',
            'email' => 'admin@example.com',
            'password' => Hash::make('123'),
            'role' => RoleEnum::ADMIN,
        ]);

        $testSupporter = User::factory()->create([
            'name' => 'Support Demo',
            'email' => 'support@example.com',
            'password' => Hash::make('123'),
            'role' => RoleEnum::SUPPORTER,
            'team_id' => $teams->first()->id,
        ]);

        $testCustomer = User::factory()->create([
            'name' => 'Customer Demo',
            'email' => 'customer@example.com',
            'password' => Hash::make('123'),
            'role' => RoleEnum::CUSTOMER,
        ]);

        /**
         * 4. Create more Random Supporters and Assign to Teams
         */
        $randomSupporters = User::factory(8)->create([
            'role' => RoleEnum::SUPPORTER,
            'team_id' => fn() => $teams->random()->id,
        ]);
        
        $allSupporters = collect([$testSupporter])->concat($randomSupporters);

        /**
         * 5. Create Random Customers
         */
        $randomCustomers = User::factory(12)->create(['role' => RoleEnum::CUSTOMER]);

        /**
         * 6. Generate Vacation Data for all Supporters
         */
        foreach ($allSupporters as $supporter) {
            // Approved vacation in current month
            $start = Carbon::now()->startOfMonth()->addDays(rand(5, 15));
            while ($start->isWeekend()) { $start->addDay(); }
            
            Vacation::create([
                'supporter_id' => $supporter->id,
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addDays(3)->toDateString(),
                'total_days' => 4,
                'year' => Carbon::now()->year,
                'status' => 'approved',
            ]);

            // Pending or Rejected vacation in next month
            $startNext = Carbon::now()->addMonth()->startOfMonth()->addDays(rand(2, 20));
            while ($startNext->isWeekend()) { $startNext->addDay(); }
            
            Vacation::create([
                'supporter_id' => $supporter->id,
                'start_date' => $startNext->toDateString(),
                'end_date' => $startNext->copy()->addDays(2)->toDateString(),
                'total_days' => 3,
                'year' => Carbon::now()->year,
                'status' => rand(0, 1) ? 'pending' : 'rejected',
            ]);
        }

        /**
         * 7. Generate Work Sessions for Support Demo (to test Time Tracking)
         */
        for ($i = 1; $i <= 7; $i++) {
            $day = Carbon::now()->subDays($i);
            if (!$day->isWeekend()) {
                WorkSession::create([
                    'user_id' => $testSupporter->id,
                    'start_time' => $day->copy()->setTime(9, 0, 0),
                    'end_time' => $day->copy()->setTime(18, 0, 0),
                    'status' => WorkSessionStatusEnum::COMPLETED->value,
                ]);
            }
        }

        /**
         * 8. Generate Tickets and Messages
         */
        $allCustomers = collect([$testCustomer])->concat($randomCustomers);
        
        foreach ($allCustomers as $customer) {
            $ticketCount = rand(1, 3);
            for ($i = 0; $i < $ticketCount; $i++) {
                $assigned = rand(0, 1) ? $allSupporters->random()->id : null;
                $ticket = Ticket::factory()->create([
                    'customer_id' => $customer->id,
                    'assigned_to' => $assigned,
                ]);

                // Initial Inquiry
                TicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $customer->id,
                    'message' => 'Hello, I am having trouble accessing the main dashboard. Can you help?',
                ]);

                // Supporter Response
                if ($assigned) {
                    TicketMessage::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $assigned,
                        'message' => 'I will check that for you immediately.',
                    ]);
                }

                // Random Tags
                if (rand(0, 1)) {
                    $ticket->tags()->attach($createdTags->random(rand(1, 2))->pluck('id'));
                }
            }
        }
    }
}