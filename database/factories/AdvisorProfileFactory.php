<?php

namespace Database\Factories;

use App\Models\AdvisorProfile;
use App\Models\InvestorAssessment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdvisorProfile>
 */
class AdvisorProfileFactory extends Factory
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
            'investor_assessment_id' => InvestorAssessment::factory()->completed(),
            'profile_version' => 1,
            'profile_payload' => [
                'persona' => 'balanced_investor',
                'scores' => ['effective_risk' => 50],
                'constraints' => [],
                'selected_assets' => [],
                'options_capability' => ['willingness' => 'no'],
            ],
            'ai_consent_at' => now(),
        ];
    }
}
