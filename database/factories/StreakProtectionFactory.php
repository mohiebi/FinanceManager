<?php

namespace Database\Factories;

use App\Enums\StreakProtectionType;
use App\Models\StreakProtection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StreakProtection>
 */
class StreakProtectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'protected_date' => today()->subDay(),
            'type' => StreakProtectionType::WeeklyGrace,
            'timezone' => 'UTC',
        ];
    }
}
