<?php

namespace Database\Factories;

use App\Enums\MilesReason;
use App\Models\MileLedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MileLedgerEntry>
 */
class MileLedgerEntryFactory extends Factory
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
            'amount' => 10,
            'balance_after' => 10,
            'reason' => MilesReason::AdminAdjustment,
            'idempotency_key' => fake()->uuid(),
            'metadata' => null,
        ];
    }
}
