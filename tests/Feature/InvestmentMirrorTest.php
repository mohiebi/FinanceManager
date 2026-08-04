<?php

use App\Enums\AssetType;
use App\Enums\Feature;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\InvestmentAsset;
use App\Models\User;

/**
 * The opt-in mirror that puts an investment purchase into the cash ledger.
 *
 * Written because a purchase logged with the box ticked produced no transaction
 * at all, which left it invisible to reports and to any budget line watching the
 * investment category.
 */
function goldAssetId(): int
{
    return InvestmentAsset::query()->where('slug', AssetType::Gold->value)->firstOrFail()->id;
}

test('a purchase recorded with the box ticked lands in the investment category', function () {
    $user = User::factory()->withModules(Feature::Investments)->create();

    $this->actingAs($user)
        ->post(route('investments.store'), [
            'investment_asset_id' => goldAssetId(),
            'quantity' => 2,
            'total_cost' => 1000000,
            'cost_basis' => 500000,
            'cost_basis_currency' => 'toman',
            'occurred_at' => now()->toDateString(),
            'record_transaction' => true,
        ])
        ->assertRedirect();

    $investmentCategory = Category::query()
        ->where('type', TransactionType::Cost)
        ->where('slug', 'investment')
        ->firstOrFail();

    $mirror = $user->transactions()->where('type', TransactionType::Cost)->first();

    expect($mirror)->not->toBeNull()
        // Without the category the mirror is invisible to every budget line and
        // to the reports breakdown — it becomes uncategorised spending.
        ->and($mirror->category_id)->toBe($investmentCategory->id)
        // The full sum, not the per-unit price: 2 units at 500,000 each.
        ->and((float) $mirror->amount)->toBe(1000000.0);
});

test('a purchase without the box writes no transaction', function () {
    $user = User::factory()->withModules(Feature::Investments)->create();

    $this->actingAs($user)
        ->post(route('investments.store'), [
            'investment_asset_id' => goldAssetId(),
            'quantity' => 2,
            'total_cost' => 1000000,
            'cost_basis' => 500000,
            'cost_basis_currency' => 'toman',
            'occurred_at' => now()->toDateString(),
        ])
        ->assertRedirect();

    expect($user->transactions()->count())->toBe(0);
});
