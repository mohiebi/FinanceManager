<?php

namespace Database\Factories;

use App\Models\MileDay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MileDay>
 */
class MileDayFactory extends Factory
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
            'local_date' => today(),
            'timezone' => 'UTC',
            'claim_step' => 0,
            'claim_miles' => 0,
            'activity_miles' => 0,
        ];
    }
}
