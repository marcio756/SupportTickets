<?php

namespace Database\Factories;

use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Ticket::class;

    /**
     * Define the model's default state.
     * Generates a random support ticket with realistic fake data.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // By default, creates a new user if no customer_id is provided
            'customer_id' => User::factory(), 
            'assigned_to' => null,
            'title' => $this->faker->sentence(6),
            'status' => $this->faker->randomElement([
                TicketStatusEnum::OPEN,
                TicketStatusEnum::IN_PROGRESS,
                TicketStatusEnum::RESOLVED,
                TicketStatusEnum::CLOSED,
            ]),
        ];
    }
}