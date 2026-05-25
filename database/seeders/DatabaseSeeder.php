<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Enums\TicketStatusEnum;
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
use Illuminate\Support\Collection;

/**
 * Class DatabaseSeeder
 * Seeds the application's database with comprehensive test data and load testing volumes.
 */
class DatabaseSeeder extends Seeder
{
    private Collection $createdTags;
    private Collection $teams;
    private User $testAdmin;
    private User $testSupporter;
    private User $testCustomer;
    private Collection $allSupporters;
    private Collection $allCustomers;

    /**
     * Executes the core database seeding lifecycle.
     */
    public function run(): void
    {
        $this->seedTags();
        $this->seedTeams();
        $this->seedCoreDemoUsers();
        $this->seedMassiveUsersAndVacations();
        $this->seedWorkSessions();
        $this->seedMassiveTickets();
    }

    /**
     * 1. Create system default tags
     */
    private function seedTags(): void
    {
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

        $this->createdTags = collect();
        foreach ($tagsData as $tag) {
            $this->createdTags->push(Tag::firstOrCreate(
                ['name' => $tag['name']],
                ['color' => $tag['color']]
            ));
        }
    }

    /**
     * 2. Create Teams
     */
    private function seedTeams(): void
    {
        $this->teams = collect([
            Team::firstOrCreate(['name' => 'Alpha Support'], ['shift' => 'morning']),
            Team::firstOrCreate(['name' => 'Beta Tech'], ['shift' => 'afternoon']),
            Team::firstOrCreate(['name' => 'Gamma Ops'], ['shift' => 'night']),
        ]);
    }

    /**
     * 3. Create Core Demo Users & Developer Account
     */
    private function seedCoreDemoUsers(): void
    {
        $this->testAdmin = User::factory()->create([
            'name' => 'Admin Demo',
            'email' => 'admin@example.com',
            'password' => Hash::make('123'),
            'role' => RoleEnum::ADMIN,
        ]);

        $this->testSupporter = User::factory()->create([
            'name' => 'Support Demo',
            'email' => 'support@example.com',
            'password' => Hash::make('123'),
            'role' => RoleEnum::SUPPORTER,
            'team_id' => $this->teams->first()->id,
        ]);

        $this->testCustomer = User::factory()->create([
            'name' => 'Customer Demo',
            'email' => 'customer@example.com',
            'password' => Hash::make('123'),
            'role' => RoleEnum::CUSTOMER,
        ]);

        // Dedicated engineering account for Pulse metrics
        User::factory()->create([
            'name' => 'Core Developer',
            'email' => 'developer@supporttickets.com',
            'password' => Hash::make('123'),
            'role' => 'developer',
        ]);
    }

    /**
     * 4 & 5 & 6. Create 1000 Supporters, 1000 Customers and generate Vacations
     */
    private function seedMassiveUsersAndVacations(): void
    {
        $randomSupporters = User::factory(1000)->create([
            'role' => RoleEnum::SUPPORTER,
            'team_id' => fn() => $this->teams->random()->id,
        ]);
        
        $this->allSupporters = collect([$this->testSupporter])->concat($randomSupporters);

        $randomCustomers = User::factory(1000)->create([
            'role' => RoleEnum::CUSTOMER
        ]);
        
        $this->allCustomers = collect([$this->testCustomer])->concat($randomCustomers);

        foreach ($this->allSupporters as $supporter) {
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
    }

    /**
     * 7. Generate Work Sessions for Support Demo
     */
    private function seedWorkSessions(): void
    {
        // Past completed sessions
        for ($i = 1; $i <= 7; $i++) {
            $day = Carbon::now()->subDays($i);
            if (!$day->isWeekend()) {
                WorkSession::create([
                    'user_id' => $this->testSupporter->id,
                    'started_at' => $day->copy()->setTime(9, 0, 0),
                    'ended_at' => $day->copy()->setTime(18, 0, 0),
                    'status' => WorkSessionStatusEnum::COMPLETED->value,
                    'total_worked_seconds' => 32400, // 9 hours * 3600 seconds
                ]);
            }
        }
        
        // Active session for today so the developer can test chat immediately
        WorkSession::create([
            'user_id' => $this->testSupporter->id,
            'started_at' => Carbon::now()->setTime(9, 0, 0),
            'ended_at' => null,
            'status' => WorkSessionStatusEnum::ACTIVE->value,
            'total_worked_seconds' => null,
        ]);
    }

    /**
     * 8. Generate 1000 Tickets and their respective Messages
     */
    private function seedMassiveTickets(): void
    {
        $statuses = [
            TicketStatusEnum::OPEN->value, 
            TicketStatusEnum::IN_PROGRESS->value, 
            TicketStatusEnum::RESOLVED->value, 
            TicketStatusEnum::CLOSED->value
        ];

        $sampleIssues = [
            "Hello, I am having trouble accessing the main dashboard. Can you help?",
            "My latest invoice seems to be incorrect. It charged me twice.",
            "Is there a way to export my data to CSV? I couldn't find the button.",
            "The system keeps crashing when I upload a PDF file larger than 5MB.",
            "I would like to request a new feature: dark mode for the mobile app."
        ];
        
        // Loop execution scaled to 1000 tickets
        for ($t = 0; $t < 1000; $t++) {
            $isEmailTicket = rand(1, 10) > 8; // 20% chance of being an email guest ticket
            $customer = $isEmailTicket ? null : $this->allCustomers->random();
            $status = $statuses[array_rand($statuses)];
            $assigned = ($status === TicketStatusEnum::OPEN->value && rand(0, 1)) ? null : $this->allSupporters->random()->id;
            $createdAt = Carbon::now()->subDays(rand(0, 30))->subHours(rand(1, 23));
            
            // Build Ticket Attributes
            $ticketAttrs = [
                'assigned_to' => $assigned,
                'status' => $status,
                'source' => $isEmailTicket ? 'email' : 'web',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if ($isEmailTicket) {
                $ticketAttrs['customer_id'] = null;
                $ticketAttrs['sender_email'] = 'guest_' . rand(10000, 99999) . '@external.com';
            } else {
                $ticketAttrs['customer_id'] = $customer->id;
                $ticketAttrs['sender_email'] = null;
            }

            $ticket = Ticket::factory()->create($ticketAttrs);

            // Add Random Tags
            if (rand(0, 10) > 2) {
                $ticket->tags()->attach($this->createdTags->random(rand(1, 3))->pluck('id'));
            }

            // Initial Inquiry
            $firstMessage = new TicketMessage([
                'ticket_id' => $ticket->id,
                'message' => $sampleIssues[array_rand($sampleIssues)],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            if ($isEmailTicket) {
                $firstMessage->user_id = null;
                $firstMessage->sender_email = $ticket->sender_email;
            } else {
                $firstMessage->user_id = $customer->id;
                $firstMessage->sender_email = null;
            }
            $firstMessage->save();

            // Supporter Response if assigned
            if ($assigned) {
                $replyTime = $createdAt->copy()->addMinutes(rand(10, 120));
                TicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $assigned,
                    'message' => 'Hello! We received your request and are looking into it. Please stand by.',
                    'created_at' => $replyTime,
                    'updated_at' => $replyTime,
                ]);

                // Simulate a Mention Scenario
                if (rand(1, 10) > 7) {
                    $mentionTime = $replyTime->copy()->addMinutes(rand(5, 30));
                    
                    TicketMessage::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $assigned,
                        'message' => 'I might need some elevated privileges to solve this. @AdminDemo could you take a look?',
                        'created_at' => $mentionTime,
                        'updated_at' => $mentionTime,
                    ]);

                    $ticket->participants()->syncWithoutDetaching([$this->testAdmin->id]);
                }
            }

            // If resolved or closed, add final message
            if (in_array($status, [TicketStatusEnum::RESOLVED->value, TicketStatusEnum::CLOSED->value])) {
                $closeTime = $createdAt->copy()->addDays(rand(1, 3));
                TicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $assigned ?? $this->testSupporter->id,
                    'message' => 'This issue has been successfully resolved. Let us know if you need anything else.',
                    'created_at' => $closeTime,
                    'updated_at' => $closeTime,
                ]);
            }
        }
    }
}