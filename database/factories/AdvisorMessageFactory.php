<?php

namespace Database\Factories;

use App\Models\AdvisorMessage;
use App\Models\AdvisorRecommendation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdvisorMessage>
 */
class AdvisorMessageFactory extends Factory
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
            'advisor_recommendation_id' => AdvisorRecommendation::factory(),
            'role' => 'user',
            'payload' => ['content' => 'Why this allocation?'],
        ];
    }
}
