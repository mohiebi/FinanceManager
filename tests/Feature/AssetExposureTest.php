<?php

use App\Actions\Investments\BuildExposureBreakdown;
use App\Actions\Investments\BuildPortfolioBreakdown;
use App\Enums\AssetClass;
use App\Enums\Currency;
use App\Enums\Feature;
use App\Mcp\Servers\FinanceServer;
use App\Mcp\Tools\ApplyFinanceChangesTool;
use App\Mcp\Tools\Investments\PortfolioSummaryTool;
use App\Models\InvestmentAsset;
use App\Models\User;
use App\Support\AssetFormulaReferences;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * The exposure link: what a custom asset says it is, and what it tracks.
 *
 * Written because the asset table was flat, so a portfolio held as bullion, half
 * coins and quarter coins read to the advisor as three tidy positions — and the
 * advice that followed was to diversify into gold.
 */
function goldRoot(): InvestmentAsset
{
    return InvestmentAsset::query()->whereNull('user_id')->where('slug', 'gold')->sole();
}

test('a custom asset can be filed under an existing market', function () {
    $user = User::factory()->withModules()->create();

    $this->actingAs($user)
        ->post(route('investment-assets.store'), [
            'name' => 'Nim half coin',
            'unit' => 'coin',
            'asset_class' => 'metal',
            'underlying_asset_id' => goldRoot()->id,
            'underlying_ratio' => 4.88,
            'price_source_type' => 'manual',
            'price_source_config' => ['price' => 118300000],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $asset = InvestmentAsset::query()->where('user_id', $user->id)->sole();

    expect($asset->asset_class)->toBe(AssetClass::Metal)
        ->and($asset->underlying_asset_id)->toBe(goldRoot()->id)
        ->and($asset->underlying_ratio)->toBe(4.88)
        ->and($asset->exposureId())->toBe(goldRoot()->id);
});

test('an asset with no underlying is its own market', function () {
    $user = User::factory()->withModules()->create();

    $this->actingAs($user)
        ->post(route('investment-assets.store'), [
            'name' => 'Company shares',
            'unit' => 'shares',
            'asset_class' => 'stock',
            'price_source_type' => 'manual',
            'price_source_config' => ['price' => 5000],
        ])
        ->assertRedirect();

    $asset = InvestmentAsset::query()->where('user_id', $user->id)->sole();

    expect($asset->underlying_asset_id)->toBeNull()
        ->and($asset->canBeUnderlying())->toBeTrue()
        ->and($asset->exposureId())->toBe($asset->id)
        // The ratio has nothing to be a ratio of, so it cannot linger.
        ->and($asset->underlying_ratio)->toBeNull();
});

test('a ratio is dropped when the underlying it described is removed', function () {
    $user = User::factory()->withModules()->create();

    $asset = InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Nim half coin',
        'unit' => 'coin',
        'asset_class' => AssetClass::Metal,
        'underlying_asset_id' => goldRoot()->id,
        'underlying_ratio' => 4.88,
        'price_source_type' => 'manual',
        'price_source_config' => ['price' => 118300000],
    ]);

    $this->actingAs($user)
        ->patch(route('investment-assets.update', $asset), [
            'name' => 'Nim half coin',
            'unit' => 'coin',
            'asset_class' => 'metal',
            'underlying_asset_id' => '',
            'underlying_ratio' => '4.88',
            'price_source_type' => 'manual',
            'price_source_config' => ['price' => 118300000],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($asset->refresh()->underlying_ratio)->toBeNull();
});

test('the tree cannot be nested more than one level', function () {
    $user = User::factory()->withModules()->create();

    $halfCoin = InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Nim half coin',
        'unit' => 'coin',
        'underlying_asset_id' => goldRoot()->id,
        'price_source_type' => 'manual',
        'price_source_config' => ['price' => 118300000],
    ]);

    $this->actingAs($user)
        ->post(route('investment-assets.store'), [
            'name' => 'Quarter coin',
            'unit' => 'coin',
            // Pointed at something that already tracks gold.
            'underlying_asset_id' => $halfCoin->id,
            'price_source_type' => 'manual',
            'price_source_config' => ['price' => 17800000],
        ])
        ->assertSessionHasErrors('underlying_asset_id');

    expect(InvestmentAsset::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('an asset other assets track cannot be given an underlying of its own', function () {
    $user = User::factory()->withModules()->create();

    $ownGold = InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Vault gold',
        'unit' => 'g',
        'price_source_type' => 'manual',
        'price_source_config' => ['price' => 5000000],
    ]);

    InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Vault half coin',
        'unit' => 'coin',
        'underlying_asset_id' => $ownGold->id,
        'price_source_type' => 'manual',
        'price_source_config' => ['price' => 24400000],
    ]);

    $this->actingAs($user)
        ->patch(route('investment-assets.update', $ownGold), [
            'name' => 'Vault gold',
            'unit' => 'g',
            'underlying_asset_id' => goldRoot()->id,
            'price_source_type' => 'manual',
            'price_source_config' => ['price' => 5000000],
        ])
        ->assertSessionHasErrors('underlying_asset_id');

    expect($ownGold->refresh()->underlying_asset_id)->toBeNull();
});

test('another user\'s asset cannot be named as an underlying', function () {
    $user = User::factory()->withModules()->create();
    $stranger = User::factory()->withModules()->create();

    $theirs = InvestmentAsset::query()->create([
        'user_id' => $stranger->id,
        'name' => 'Their gold',
        'unit' => 'g',
        'price_source_type' => 'manual',
        'price_source_config' => ['price' => 5000000],
    ]);

    $this->actingAs($user)
        ->post(route('investment-assets.store'), [
            'name' => 'Nim half coin',
            'unit' => 'coin',
            'underlying_asset_id' => $theirs->id,
            'price_source_type' => 'manual',
            'price_source_config' => ['price' => 118300000],
        ])
        ->assertSessionHasErrors('underlying_asset_id');
});

/**
 * The model is the backstop for writers that never see the form request — the
 * MCP proposal path among them. It flattens rather than rejects: if a 250g
 * silver bar is declared to track a 100g bar, the honest answer is silver.
 */
test('a nested link is flattened to the root it really tracks', function () {
    $user = User::factory()->withModules()->create();

    $halfCoin = InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Nim half coin',
        'unit' => 'coin',
        'underlying_asset_id' => goldRoot()->id,
        'underlying_ratio' => 4.88,
        'price_source_type' => 'manual',
        'price_source_config' => ['price' => 118300000],
    ]);

    $quarter = InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Rob quarter coin',
        'unit' => 'coin',
        'underlying_asset_id' => $halfCoin->id,
        'underlying_ratio' => 0.5,
        'price_source_type' => 'manual',
        'price_source_config' => ['price' => 17800000],
    ]);

    expect($quarter->underlying_asset_id)->toBe(goldRoot()->id)
        // The ratio described a hop that no longer exists; chaining two of them
        // would be a fabricated number.
        ->and($quarter->underlying_ratio)->toBeNull();
});

test('an asset cannot be made to track itself', function () {
    $user = User::factory()->withModules()->create();

    $asset = InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Vault gold',
        'unit' => 'g',
        'price_source_type' => 'manual',
        'price_source_config' => ['price' => 5000000],
    ]);

    $asset->update(['underlying_asset_id' => $asset->id, 'underlying_ratio' => 1]);

    expect($asset->refresh()->underlying_asset_id)->toBeNull();
});

test('the settings page offers only roots as an underlying', function () {
    $user = User::factory()->withModules()->create();

    $ownGold = InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Vault gold',
        'unit' => 'g',
        'price_source_type' => 'manual',
        'price_source_config' => ['price' => 5000000],
    ]);

    InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Vault half coin',
        'unit' => 'coin',
        'underlying_asset_id' => $ownGold->id,
        'price_source_type' => 'manual',
        'price_source_config' => ['price' => 24400000],
    ]);

    $this->actingAs($user)
        ->get(route('investment-assets.edit'))
        ->assertInertia(function ($page) {
            $slugs = array_column($page->toArray()['props']['underlyingOptions'], 'slug');

            expect($slugs)->toContain('gold', 'vault-gold')
                ->and($slugs)->not->toContain('vault-half-coin');
        });
});

test('the AI is told which market each holding tracks, and the roll-up', function () {
    $user = User::factory()->withModules(Feature::Investments, Feature::Portfolio)->create();

    $ownGold = InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Vault gold',
        'unit' => 'g',
        'asset_class' => AssetClass::Metal,
        'price_source_type' => 'manual',
        'price_source_config' => ['price' => 5000000],
    ]);

    $halfCoin = InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Nim half coin',
        'unit' => 'coin',
        'asset_class' => AssetClass::Metal,
        'underlying_asset_id' => $ownGold->id,
        'underlying_ratio' => 4.88,
        'price_source_type' => 'manual',
        'price_source_config' => ['price' => 24400000],
    ]);

    foreach ([[$ownGold, 2], [$halfCoin, 1]] as [$asset, $quantity]) {
        $user->investments()->create([
            'investment_asset_id' => $asset->id,
            'asset_type' => $asset->slug,
            'kind' => 'buy',
            'quantity' => $quantity,
            'occurred_at' => '2026-07-01',
        ]);
    }

    $breakdown = app(BuildPortfolioBreakdown::class);
    $result = app(BuildExposureBreakdown::class)->handle(
        $breakdown->handle($breakdown->entriesFor($user), Currency::Toman)['assets'],
        $breakdown->formatter(Currency::Toman),
    );

    expect($result['exposures'])->toHaveCount(1);

    $gold = $result['exposures'][0];

    expect($gold['label'])->toBe('Vault gold')
        // 2 grams held directly plus one coin worth 4.88 of them.
        ->and($gold['equivalent_quantity'])->toBe(6.88)
        ->and($gold['equivalent_unit'])->toBe('g')
        ->and($gold['percent'])->toBe(100.0)
        ->and($gold['value'])->toBe(34400000.0)
        ->and($result['classes'])->toHaveCount(1)
        ->and($result['classes'][0]['key'])->toBe('metal')
        // The point of the whole change: two rows, one bet.
        ->and($result['classes'][0]['asset_count'])->toBe(2)
        ->and($result['classes'][0]['exposure_count'])->toBe(1);
});

test('a formula states its own exposure', function () {
    $references = app(AssetFormulaReferences::class);

    expect($references->soleAssetSlugIn('goldprice * 900 / 750 * 8.133 / 2'))->toBe('gold')
        ->and($references->soleAssetSlugIn('silver_ounce_usd * usd / 31.1'))->toBeNull()
        ->and($references->soleAssetSlugIn('1000000'))->toBeNull()
        // A full Bahar Azadi is gold; there is no coin row to point at, and
        // pointing it at gold is the more truthful answer anyway.
        ->and($references->soleAssetSlugIn('coin * 0.5'))->toBe('gold');
});

test('a linear formula gives up its conversion, a non-linear one does not', function () {
    $references = app(AssetFormulaReferences::class);

    expect($references->ratioToUnderlying('gold * 4.88'))->toBe(4.88)
        ->and($references->ratioToUnderlying('goldprice * 900 / 750 * 8.133 / 2'))->toBe(4.8798)
        // Adding a constant makes the "ratio" drift with the market, so there
        // is no honest single number to report.
        ->and($references->ratioToUnderlying('gold * 4.88 + 100000'))->toBeNull()
        ->and($references->ratioToUnderlying('silver * usd'))->toBeNull();
});

test('portfolio-summary hands the AI the roll-up, not just the rows', function () {
    $user = User::factory()->withModules(Feature::Investments, Feature::Portfolio)->create();

    $ownGold = InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Vault gold',
        'unit' => 'g',
        'asset_class' => AssetClass::Metal,
        'price_source_type' => 'manual',
        'price_source_config' => ['price' => 5000000],
    ]);

    $halfCoin = InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Nim half coin',
        'unit' => 'coin',
        'asset_class' => AssetClass::Metal,
        'underlying_asset_id' => $ownGold->id,
        'underlying_ratio' => 4.88,
        'price_source_type' => 'manual',
        'price_source_config' => ['price' => 24400000],
    ]);

    foreach ([[$ownGold, 2], [$halfCoin, 1]] as [$asset, $quantity]) {
        $user->investments()->create([
            'investment_asset_id' => $asset->id,
            'asset_type' => $asset->slug,
            'kind' => 'buy',
            'quantity' => $quantity,
            'occurred_at' => '2026-07-01',
        ]);
    }

    FinanceServer::actingAs($user)
        ->tool(PortfolioSummaryTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $content) => $content
            // Two holdings — the larger first, so the coin leads.
            ->where('summary.asset_count', 2)
            ->where('assets.0.asset', 'Nim half coin')
            ->where('assets.0.tracks', 'Vault gold')
            ->where('assets.0.units_of_tracked_asset_each', 4.88)
            ->where('assets.1.tracks', null)
            // ...one bet.
            ->has('exposures', 1)
            ->where('exposures.0.market', 'Vault gold')
            ->where('exposures.0.percent_of_portfolio', 100.0)
            ->where('exposures.0.total_units', 6.88)
            ->where('exposures.0.unit', 'g')
            ->where('classes.0.class', 'metal')
            ->where('classes.0.distinct_markets', 1)
            ->etc());
});

test('an AI client can file the asset it creates under an existing market', function () {
    $user = User::factory()->withModules(Feature::Investments)->create();

    FinanceServer::actingAs($user)
        ->tool(ApplyFinanceChangesTool::class, [
            'operations' => [[
                'action' => 'create',
                'resource' => 'investment_asset',
                'name' => 'Nim half coin',
                'unit' => 'coin',
                'asset_class' => 'metal',
                'tracks_asset_slug' => 'gold',
                'units_of_tracked_asset_each' => 4.88,
                'price_source_type' => 'manual',
                'price' => 118300000,
            ]],
        ])
        ->assertOk();

    $asset = InvestmentAsset::query()->where('user_id', $user->id)->sole();

    expect($asset->asset_class)->toBe(AssetClass::Metal)
        ->and($asset->underlying_asset_id)->toBe(goldRoot()->id)
        ->and($asset->underlying_ratio)->toBe(4.88);
});

/**
 * A batch is applied without a human reading each operation back, so a mistyped
 * link has to stop rather than quietly produce an unlinked asset.
 */
test('a batch naming an unknown market is refused rather than half-applied', function () {
    $user = User::factory()->withModules(Feature::Investments)->create();

    FinanceServer::actingAs($user)
        ->tool(ApplyFinanceChangesTool::class, [
            'operations' => [[
                'action' => 'create',
                'resource' => 'investment_asset',
                'name' => 'Nim half coin',
                'unit' => 'coin',
                'tracks_asset_slug' => 'palladium',
                'price_source_type' => 'manual',
                'price' => 118300000,
            ]],
        ])
        ->assertHasErrors();

    expect(InvestmentAsset::query()->where('user_id', $user->id)->exists())->toBeFalse();
});
