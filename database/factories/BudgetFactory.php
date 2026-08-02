<?php

namespace Database\Factories;

use App\Enums\BudgetIncomeBasis;
use App\Enums\Currency;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => 'Monthly plan',
            'income_basis' => BudgetIncomeBasis::Actual,
            'expected_income' => null,
            'currency' => Currency::Toman,
            'starts_on' => Carbon::today()->startOfMonth()->toDateString(),
            'is_active' => true,
        ];
    }

    /**
     * A plan with a declared income, so targets exist before any income lands.
     */
    public function expecting(float $income): static
    {
        return $this->state(fn (array $attributes): array => [
            'income_basis' => BudgetIncomeBasis::Expected,
            'expected_income' => $income,
        ]);
    }
}
