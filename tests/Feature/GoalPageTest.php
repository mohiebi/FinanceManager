<?php

use App\Actions\Goals\BuildGoalProgress;
use App\Actions\Investments\BuildPortfolioBreakdown;
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
    $user = User::factory()->withModules(Feature::Portfolio, Feature::Goals)->create();

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
    $user = User::factory()->withModules(Feature::Portfolio, Feature::Goals)->create();

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
    $user = User::factory()->withModules(Feature::Portfolio, Feature::Goals)->create();
    $stranger = User::factory()->withModules(Feature::Portfolio, Feature::Goals)->create();

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

test('the page is gated on the goals module, not the portfolio one', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('goals'))
        ->assertRedirect();

    // Goals are their own module: someone who tracks grams of gold should not
    // have to switch on net worth and P&L to do it.
    $this->actingAs(User::factory()->withModules(Feature::Portfolio)->create())
        ->get(route('goals'))
        ->assertRedirect();

    $this->actingAs(User::factory()->withModules(Feature::Goals)->create())
        ->get(route('goals'))
        ->assertOk();
});

test('the portfolio page drops the goals section when the module is off', function () {
    $user = User::factory()->withModules(Feature::Portfolio)->create();

    SavingsGoal::factory()->create([
        'user_id' => $user->id,
        'investment_asset_id' => goldAssetForPage()->id,
        'title' => 'Nowruz fund',
    ]);

    // Asserted on the props rather than the rendered HTML: the page is an empty
    // Inertia shell in tests, so the payload is the only place a leak could show.
    $this->actingAs($user)
        ->get(route('portfolio'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('showsGoals', false)
            // An empty list rather than a deferred key: nothing will ever arrive
            // to fill it, and the page must not wait for it.
            ->where('goals', [])
            ->etc());
});

test('an edited goal comes back with its new values on the next render', function () {
    $user = User::factory()->withModules(Feature::Goals)->create();

    $goal = SavingsGoal::factory()->create([
        'user_id' => $user->id,
        'investment_asset_id' => goldAssetForPage()->id,
        'title' => 'Nowruz fund',
        'target_quantity' => 3,
    ]);

    $this->actingAs($user)
        ->put(route('savings-goals.update', $goal), [
            'investment_asset_id' => goldAssetForPage()->id,
            'title' => 'Renamed fund',
            'target_quantity' => 9,
            'target_date' => now()->addDays(120)->toDateString(),
        ])
        ->assertRedirect();

    // The write landed; the question is whether the page hands the new values
    // back, because the card was still showing the old ones.
    $this->actingAs($user)
        ->get(route('goals'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('goals.0.title', 'Renamed fund')
            ->where('goals.0.target_quantity', 9)
            ->etc());
});

test('the portfolio drops achievements older than the window', function () {
    $user = User::factory()->withModules(Feature::Portfolio, Feature::Goals)->create();

    SavingsGoal::factory()->create([
        'user_id' => $user->id,
        'investment_asset_id' => goldAssetForPage()->id,
        'title' => 'Finished long ago',
        'target_quantity' => 1,
        'achieved_on' => now()->subMonths(9)->toDateString(),
    ]);
    SavingsGoal::factory()->create([
        'user_id' => $user->id,
        'investment_asset_id' => goldAssetForPage()->id,
        'title' => 'Still going',
        'target_quantity' => 99,
    ]);

    holdGold($user, 2, now()->toDateString());

    $this->actingAs($user)
        ->get(route('portfolio'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('showsGoals', true)
            ->etc());

    // Deferred, so the list arrives on the follow-up request.
    $this->actingAs($user)
        ->get(route('portfolio'), ['X-Inertia-Partial-Data' => 'goals'])
        ->assertOk();

    $recent = app(BuildGoalProgress::class)->forPortfolio(
        $user,
        app(BuildPortfolioBreakdown::class)->entriesFor($user),
    );

    expect(collect($recent)->pluck('title')->all())
        ->toContain('Still going')
        ->not->toContain('Finished long ago');
});

test('the goals page still shows an old achievement', function () {
    $user = User::factory()->withModules(Feature::Portfolio, Feature::Goals)->create();

    SavingsGoal::factory()->create([
        'user_id' => $user->id,
        'investment_asset_id' => goldAssetForPage()->id,
        'title' => 'Finished long ago',
        'target_quantity' => 1,
        'achieved_on' => now()->subMonths(9)->toDateString(),
    ]);

    holdGold($user, 2, now()->toDateString());

    $this->actingAs($user)
        ->get(route('goals'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('goals', 1)
            ->where('goals.0.title', 'Finished long ago')
            ->where('goals.0.reached', true)
            ->etc());
});
