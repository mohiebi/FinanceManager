<?php

use App\Enums\AssetType;
use App\Enums\Feature;
use App\Models\InvestmentAsset;
use App\Models\SavingsGoal;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The portfolio page's prop contract.
 *
 * Everything the goals feature renders arrives through here, and the armed and
 * unarmed branches ship different keys — so a dropped or renamed key is a broken
 * page that no unit test would notice.
 */
test('the unarmed page defers goals and asset options', function () {
    $user = User::factory()->withModules(Feature::Portfolio, Feature::Goals)->create();

    SavingsGoal::factory()->for($user)->create([
        'investment_asset_id' => InvestmentAsset::query()
            ->where('slug', AssetType::Gold->value)
            ->firstOrFail()
            ->id,
    ]);

    $this->actingAs($user)
        ->get(route('portfolio'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portfolio')
            // Deferred, so absent on first paint and fetched right after.
            ->missing('goals')
            ->missing('assetOptions')
            // Only the armed branch sends this one.
            ->missing('vaultGoals')
            ->etc());
});

test('the deferred goals keys are registered so the follow-up request fires', function () {
    $user = User::factory()->withModules(Feature::Portfolio, Feature::Goals)->create();

    SavingsGoal::factory()->for($user)->create([
        'investment_asset_id' => InvestmentAsset::query()
            ->where('slug', AssetType::Gold->value)
            ->firstOrFail()
            ->id,
    ]);

    $page = $this->actingAs($user)->get(route('portfolio'))->viewData('page');
    $deferred = collect($page['deferredProps'] ?? [])->flatten()->all();

    // Omitting a key drops it from the manifest entirely, so nothing ever
    // requests it and the page waits on a prop that will never arrive.
    expect($deferred)->toContain('goals')
        ->and($deferred)->toContain('assetOptions');
});

// The armed branch of this page is covered in VaultDegradedModeTest, which owns
// the vault helpers.
