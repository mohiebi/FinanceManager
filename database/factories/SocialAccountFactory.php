<?php

namespace Database\Factories;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
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
            'provider' => SocialAccount::ProviderGoogle,
            'provider_user_id' => (string) fake()->unique()->numberBetween(100000, 999999),
            'provider_email' => fake()->unique()->safeEmail(),
            'avatar' => fake()->imageUrl(256, 256, 'people'),
        ];
    }
}
