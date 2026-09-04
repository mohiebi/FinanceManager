<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserCosmetic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserCosmetic>
 */
class UserCosmeticFactory extends Factory
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
            'key' => 'callsign_pathfinder',
            'type' => 'callsign',
            'selected' => true,
            'acquired_at' => now(),
        ];
    }
}
