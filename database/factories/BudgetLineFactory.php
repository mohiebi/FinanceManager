<?php

namespace Database\Factories;

use App\Enums\BudgetRuleType;
use App\Enums\Currency;
use App\Models\Budget;
use App\Models\BudgetLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetLine>
 */
class BudgetLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'category_id' => null,
            'rule_type' => BudgetRuleType::Percent,
            'percent' => 50,
            'fixed_amount' => null,
            'currency' => null,
            'rollover_enabled' => false,
            'sort_order' => 0,
        ];
    }

    public function percent(float $percent): static
    {
        return $this->state(fn (array $attributes): array => [
            'rule_type' => BudgetRuleType::Percent,
            'percent' => $percent,
            'fixed_amount' => null,
        ]);
    }

    public function fixed(float $amount, ?Currency $currency = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'rule_type' => BudgetRuleType::Fixed,
            'percent' => null,
            'fixed_amount' => $amount,
            // Null means the budget's own currency, which is what a plan written
            // entirely in one currency has.
            'currency' => $currency,
        ]);
    }

    public function remainder(): static
    {
        return $this->state(fn (array $attributes): array => [
            'rule_type' => BudgetRuleType::Remainder,
            'percent' => null,
            'fixed_amount' => null,
        ]);
    }
}
