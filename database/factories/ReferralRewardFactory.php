<?php

namespace Database\Factories;

use App\Enums\ReferralStage;
use App\Models\Referral;
use App\Models\ReferralReward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralReward>
 */
class ReferralRewardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'referral_id' => Referral::factory(),
            'stage' => ReferralStage::Activated,
            'awarded_at' => now(),
        ];
    }
}
