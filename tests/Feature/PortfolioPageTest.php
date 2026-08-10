<?php

use App\Enums\AssetType;
use App\Enums\Currency;
use App\Enums\Feature;
use App\Models\AssetPriceSnapshot;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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

test('the value-over-time chart prices past dates from snapshots and today from the live price', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-06 12:00:00'));
    Cache::flush();

    try {
        config(['services.tgju.enabled' => true]);
        // Live price today.
        Cache::put('asset-prices.tgju', ['usd' => 150000.0], now()->addMinutes(5));

        $user = User::factory()->withModules()->create();
        $usdAsset = InvestmentAsset::query()->where('slug', 'usd')->firstOrFail();

        Investment::query()->create([
            'user_id' => $user->id,
            'investment_asset_id' => $usdAsset->id,
            'asset_type' => AssetType::Usd->value,
            'quantity' => 100,
            'cost_basis' => 100000,
            'cost_basis_currency' => Currency::Toman->value,
            'occurred_at' => '2026-06-01',
        ]);

        // A recorded historical price, three days ago, lower than today's live price.
        AssetPriceSnapshot::query()->create([
            'investment_asset_id' => $usdAsset->id,
            'price' => 120000.0,
            'snapped_on' => '2026-07-03',
        ]);

        $this->actingAs($user)
            ->get(route('portfolio', ['range' => '1w']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portfolio')
                ->loadDeferredProps('default', fn (Assert $page) => $page
                    ->where('chartData', function (mixed $chartData): bool {
                        $data = collect($chartData);
                        $dates = collect($data->get('categories'));
                        $total = collect($data->get('series'))->firstWhere('key', 'total');
                        $values = collect($total['data']);

                        $valueOn = fn (string $date) => (float) $values->get($dates->search($date));

                        // Snapshot day: 100 units × 120,000 historical price.
                        expect($valueOn('2026-07-03'))->toBe(12000000.0)
                            // Today: 100 units × 150,000 live price.
                            ->and($valueOn('2026-07-06'))->toBe(15000000.0);

                        return true;
                    }),
                ),
            );
    } finally {
        Carbon::setTestNow();
    }
});

// The armed branch of this page is covered in VaultDegradedModeTest, which owns
// the vault helpers.
