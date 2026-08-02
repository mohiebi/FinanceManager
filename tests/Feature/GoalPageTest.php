<?php

use App\Enums\AssetType;
use App\Enums\Feature;
use App\Models\InvestmentAsset;
use App\Models\SavingsGoal;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function goldAssetForPage(): InvestmentAsset
{
    return InvestmentAsset::query()->where('slug', AssetType::Gold->value)->firstOrFail();
}

function holdGold(User $user, float $quantity, string $on = '2026-07-01'): void
{
    $user->investments()->create([
        'investment_asset_id' => goldAssetForPage()->id,
        'asset_type' => AssetType::Gold->value,
        'kind' => 'buy',
        'quantity' => $quantity,
        'occurred_at' => $on,
    ]);
}

test('the goals page lists every goal with its progress', function () {
    $user = User::factory()->withModules(Feature::Portfolio)->create();

    SavingsGoal::factory()->create([
        'user_id' => $user->id,
        'investment_asset_id' => goldAssetForPage()->id,
        'title' => 'Nowruz fund',
        'target_quantity' => 3,
    ]);

    holdGold($user, 1.5);

    $this->actingAs($user)
        ->get(route('goals'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Goals')
            ->has('goals', 1)
            ->where('goals.0.title', 'Nowruz fund')
            ->where('goals.0.reached', false)
            // Not deferred here, unlike the portfolio: this page exists to edit
            // goals, so the dialog is opened on most visits.
            ->has('assetOptions')
            ->where('vaultGoals', null)
        );
});

test('a met goal is reported as reached so the page can file it separately', function () {
    $user = User::factory()->withModules(Feature::Portfolio)->create();

    SavingsGoal::factory()->create([
        'user_id' => $user->id,
        'investment_asset_id' => goldAssetForPage()->id,
        'title' => 'Done and dusted',
        'target_quantity' => 2,
    ]);

    holdGold($user, 2.4);

    $this->actingAs($user)
        ->get(route('goals'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('goals.0.reached', true)
            ->etc());
});

test('the page never carries another users goals', function () {
    $user = User::factory()->withModules(Feature::Portfolio)->create();
    $stranger = User::factory()->withModules(Feature::Portfolio)->create();

    SavingsGoal::factory()->create([
        'user_id' => $stranger->id,
        'investment_asset_id' => goldAssetForPage()->id,
        'title' => 'Their private goal',
    ]);

    $this->actingAs($user)
        ->get(route('goals'))
        ->assertOk()
        ->assertDontSee('Their private goal')
        ->assertInertia(fn (Assert $page) => $page->has('goals', 0)->etc());
});

test('the page is gated on the portfolio module', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('goals'))
        ->assertRedirect();

    $this->actingAs(User::factory()->withModules(Feature::Portfolio)->create())
        ->get(route('goals'))
        ->assertOk();
});
