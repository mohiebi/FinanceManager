<?php

namespace Database\Factories;

use App\Models\MileWallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MileWallet>
 */
class MileWalletFactory extends Factory
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
            'balance' => 0,
            'lifetime_earned' => 0,
            'lifetime_spent' => 0,
            'freezes_held' => 0,
        ];
    }
}
