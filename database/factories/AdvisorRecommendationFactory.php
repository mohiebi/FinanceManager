<?php

namespace Database\Factories;

use App\Enums\AdvisorRecommendationMode;
use App\Enums\AdvisorRecommendationStatus;
use App\Models\AdvisorProfile;
use App\Models\AdvisorRecommendation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdvisorRecommendation>
 */
class AdvisorRecommendationFactory extends Factory
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
            'advisor_profile_id' => AdvisorProfile::factory(),
            'status' => AdvisorRecommendationStatus::Ready,
            'mode' => AdvisorRecommendationMode::TargetOnly,
            'profile_version' => 1,
            'scoring_version' => 1,
            'prompt_version' => 1,
            'provider' => 'openai',
            'model' => 'test-model',
            'knowledge_version' => 1,
            'context_hash' => hash('sha256', 'context'),
            'output_hash' => hash('sha256', 'output'),
            'current_portfolio_included' => false,
            'recommendation_payload' => ['status' => 'recommendation_ready'],
            'generated_at' => now(),
        ];
    }
}
