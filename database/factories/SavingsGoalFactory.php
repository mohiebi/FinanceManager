<?php

namespace Database\Factories;

use App\Enums\AssetType;
use App\Models\InvestmentAsset;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SavingsGoal>
 */
class SavingsGoalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // Resolved from the seeded defaults rather than built: InvestmentAsset
            // has no factory, and every test in this codebase looks assets up by slug.
            'investment_asset_id' => fn (): int => InvestmentAsset::query()
                ->where('slug', AssetType::Gold->value)
                ->firstOrFail()
                ->id,
            'title' => 'Nowruz fund',
            'target_quantity' => 3,
            'started_on' => Carbon::today()->toDateString(),
            'target_date' => Carbon::today()->addDays(180)->toDateString(),
            'is_active' => true,
        ];
    }

    /**
     * A goal already part-way through its window, for pace assertions.
     */
    public function started(int $daysAgo, int $daysAhead = 80): static
    {
        return $this->state(fn (array $attributes): array => [
            'started_on' => Carbon::today()->subDays($daysAgo)->toDateString(),
            'target_date' => Carbon::today()->addDays($daysAhead)->toDateString(),
        ]);
    }
}
