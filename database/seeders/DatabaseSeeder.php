<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Orchestrates the creation of users, standard tags, and mock tickets for development.
     */
    public function run(): void
    {
        /**
         * 1. Define and create system default tags
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
         * 2. Create the specific testing Customer
         */
        $testCustomer = User::factory()->create([
            'name' => 'Customer Demo',
            'email' => 'customer@example.com',
            'password' => Hash::make('123'),
            'role' => RoleEnum::CUSTOMER,
        ]);

        /**
         * 3. Create the specific testing Supporter
         */
        $testSupporter = User::factory()->create([
            'name' => 'Support Demo',
            'email' => 'support@example.com',
            'password' => Hash::make('123'),
            'role' => RoleEnum::SUPPORTER,
        ]);

        /**
         * 4. Create 10 random Customers
         */
        $randomCustomers = User::factory(10)->create([
            'role' => RoleEnum::CUSTOMER,
        ]);

        /**
         * 5. Create 5 random Supporters
         */
        $randomSupporters = User::factory(5)->create([
            'role' => RoleEnum::SUPPORTER,
        ]);

        /**
         * 6. Generate mock tickets to populate the UI tables
         */
        Ticket::factory(3)->create([
            'customer_id' => $testCustomer->id,
            'assigned_to' => $testSupporter->id,
        ]);

        Ticket::factory(2)->create([
            'customer_id' => $testCustomer->id,
            'assigned_to' => null,
        ]);

        foreach ($randomCustomers as $customer) {
            Ticket::factory(rand(1, 5))->create([
                'customer_id' => $customer->id,
                'assigned_to' => rand(0, 1) ? $randomSupporters->random()->id : null,
            ]);
        }

        /**
         * 7. Attach random tags to generated tickets
         * We iterate over all tickets and randomly assign between 1 to 3 tags to ~80% of them.
         */
        $allTickets = Ticket::all();
        foreach ($allTickets as $ticket) {
            if (rand(1, 100) <= 80) {
                $randomTags = $createdTags->random(rand(1, 3))->pluck('id');
                $ticket->tags()->attach($randomTags);
            }
        }
    }
}