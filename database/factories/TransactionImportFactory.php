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
            'result' => null,
            'consumed_at' => null,
        ];
    }
}
