<?php

namespace Database\Factories;

use App\Enums\InvestorAssessmentStatus;
use App\Models\InvestorAssessment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestorAssessment>
 */
class InvestorAssessmentFactory extends Factory
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
            'status' => InvestorAssessmentStatus::InProgress,
            'assessment_version' => 1,
            'scoring_version' => 1,
            'last_completed_section' => 0,
            'scoring_origin' => null,
            'started_at' => now(),
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => InvestorAssessmentStatus::Completed,
            'last_completed_section' => 8,
            'scoring_origin' => 'server',
            'completed_at' => now(),
        ]);
    }
}
