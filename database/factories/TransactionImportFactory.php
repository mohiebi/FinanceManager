<?php

namespace Database\Factories;

use App\Models\TransactionImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionImport>
 */
class TransactionImportFactory extends Factory
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
            'rows' => [],
            'summary' => [
                'total' => 0,
                'valid' => 0,
                'invalid' => 0,
                'duplicate' => 0,
                'importable' => 0,
            ],
            'claimed' => false,
            'result' => null,
            'expires_at' => now()->addDay(),
        ];
    }

    /**
     * A preview that has already been confirmed, so replaying it returns a receipt.
     */
    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'rows' => null,
            'claimed' => true,
            'result' => [
                'imported' => 0,
                'skipped' => 0,
                'skipped_duplicates' => 0,
                'skipped_invalid' => 0,
            ],
        ]);
    }

    /**
     * A preview past its expiry, which can no longer be confirmed.
     */
    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }
}
