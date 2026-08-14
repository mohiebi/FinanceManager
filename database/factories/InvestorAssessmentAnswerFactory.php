<?php

namespace Database\Factories;

use App\Models\InvestorAssessment;
use App\Models\InvestorAssessmentAnswer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestorAssessmentAnswer>
 */
class InvestorAssessmentAnswerFactory extends Factory
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
            'investor_assessment_id' => InvestorAssessment::factory(),
            'question_key' => 'q1_age',
            'answer' => '35_44',
        ];
    }
}
