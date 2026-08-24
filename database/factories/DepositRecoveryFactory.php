<?php

namespace Database\Factories;

use App\Enums\DepositAddressStatus;
use App\Enums\PaymentNetwork;
use App\Enums\SettlementStatus;
use App\Models\DepositAddress;
use App\Models\DepositRecovery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DepositRecovery>
 */
class DepositRecoveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'deposit_address_id' => DepositAddress::factory()->state([
                'status' => DepositAddressStatus::Retired,
            ]),
            'network' => PaymentNetwork::Ethereum,
            'operation_id' => (string) Str::uuid(),
            'status' => SettlementStatus::Queued,
            'reason' => fake()->sentence(),
        ];
    }
}
