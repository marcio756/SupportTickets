<?php

namespace Database\Factories;

use App\Models\WorkSession;
use App\Models\User;
use App\Enums\WorkSessionStatusEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkSession>
 */
class WorkSessionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = WorkSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => WorkSessionStatusEnum::ACTIVE->value,
            'started_at' => now(),
            'ended_at' => null,
            'total_worked_seconds' => null,
        ];
    }
}