<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Orchestrates the creation of users and mock tickets for development.
     */
    public function run(): void
    {
        /**
         * 1. Create the specific testing Customer
         */
        $testCustomer = User::factory()->create([
            'name' => 'Customer Demo',
            'email' => 'customer@example.com',
            'password' => Hash::make('123'),
            'role' => RoleEnum::CUSTOMER,
        ]);

        /**
         * 2. Create the specific testing Supporter
         */
        $testSupporter = User::factory()->create([
            'name' => 'Support Demo',
            'email' => 'support@example.com',
            'password' => Hash::make('123'),
            'role' => RoleEnum::SUPPORTER,
        ]);

        /**
         * 3. Create 10 random Customers
         */
        $randomCustomers = User::factory(10)->create([
            'role' => RoleEnum::CUSTOMER,
        ]);

        /**
         * 4. Create 5 random Supporters
         */
        $randomSupporters = User::factory(5)->create([
            'role' => RoleEnum::SUPPORTER,
        ]);

        /**
         * 5. Generate mock tickets to populate the UI tables
         */
        
        // Give our main test customer 5 tickets (3 assigned to our test support, 2 unassigned)
        Ticket::factory(3)->create([
            'customer_id' => $testCustomer->id,
            'assigned_to' => $testSupporter->id,
        ]);

        Ticket::factory(2)->create([
            'customer_id' => $testCustomer->id,
            'assigned_to' => null,
        ]);

        // Distribute random tickets among the 10 random customers
        foreach ($randomCustomers as $customer) {
            Ticket::factory(rand(1, 5))->create([
                'customer_id' => $customer->id,
                // 50% chance of being assigned to a random supporter
                'assigned_to' => rand(0, 1) ? $randomSupporters->random()->id : null,
            ]);
        }
    }
}