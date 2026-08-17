<?php

namespace Database\Factories;

use App\Enums\ScreeningRisk;
use App\Enums\ScreeningStage;
use App\Models\PaymentScreening;
use App\Models\SubscriptionPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentScreening>
 */
class PaymentScreeningFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_payment_id' => SubscriptionPayment::factory(),
            'stage' => ScreeningStage::Settlement,
            'risk' => ScreeningRisk::NoMatch,
            'provider' => 'fake',
            'categories' => [],
            'screened_at' => now(),
        ];
    }
}
