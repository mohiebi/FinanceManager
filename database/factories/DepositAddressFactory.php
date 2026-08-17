<?php

namespace Database\Factories;

use App\Enums\DepositAddressStatus;
use App\Enums\PaymentNetwork;
use App\Models\DepositAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepositAddress>
 */
class DepositAddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'network' => PaymentNetwork::Ethereum,
            'derivation_index' => fake()->unique()->numberBetween(1, 1_000_000),
            'address' => '0x'.fake()->unique()->regexify('[0-9a-f]{40}'),
            'status' => DepositAddressStatus::Available,
        ];
    }
}
