<?php

namespace Database\Factories;

use App\Models\AuthChallenge;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<AuthChallenge>
 */
class AuthChallengeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->safeEmail(),
            'purpose' => AuthChallenge::PurposeSignup,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => null,
            'consumed_at' => null,
            'attempts' => 0,
            'last_sent_at' => now(),
            'ip_address' => fake()->ipv4(),
        ];
    }
}
