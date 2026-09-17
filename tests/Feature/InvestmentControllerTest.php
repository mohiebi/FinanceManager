<?php

use App\Actions\Investments\SaveInvestment;
use App\Enums\AssetType;
use App\Enums\Currency;
use App\Models\Category;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

test('it stores total investment cost as per unit cost basis', function () {
    $user = User::factory()->withModules()->create();

    $this->actingAs($user)
        ->post(route('investments.store'), [
            'asset_type' => AssetType::Gold->value,
            'quantity' => '2',
            'total_cost' => '1000000',
            'cost_basis_currency' => Currency::Toman->value,
            'occurred_at' => now()->toDateString(),
        ])
        ->assertRedirect();

    $investment = Investment::query()->sole();

    expect((float) $investment->cost_basis)->toBe(500000.0)
        ->and($investment->cost_basis_currency)->toBe(Currency::Toman->value)
        ->and($investment->asset_type)->toBe(AssetType::Gold->value)
        ->and($investment->investment_asset_id)->not->toBeNull();
});

test('it updates total investment cost as per unit cost basis', function () {
    $user = User::factory()->withModules()->create();
    $asset = InvestmentAsset::query()->where('slug', AssetType::Silver->value)->firstOrFail();
    $investment = Investment::query()->create([
        'user_id' => $user->id,
        'investment_asset_id' => $asset->id,
        'asset_type' => AssetType::Silver->value,
        'quantity' => 10,
        'cost_basis' => 100,
        'cost_basis_currency' => Currency::Toman->value,
        'occurred_at' => now()->subDay()->toDateString(),
    ]);

    $this->actingAs($user)
        ->patch(route('investments.update', $investment), [
            'asset_type' => AssetType::Silver->value,
            'quantity' => '4',
            'total_cost' => '1000',
            'cost_basis_currency' => Currency::Toman->value,
            'occurred_at' => now()->toDateString(),
        ])
        ->assertRedirect();

    $investment->refresh();

    expect((float) $investment->cost_basis)->toBe(250.0);
});

test('it stores investments for custom assets', function () {
    $user = User::factory()->withModules()->create();
    $asset = InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Nim half coin',
        'unit' => 'coin',
        'color' => '#02CD86',
        'price_source_type' => 'formula',
        'price_source_config' => ['formula' => 'goldprice * 900 / 750 * 8.133 / 2'],
    ]);

    $this->actingAs($user)
        ->post(route('investments.store'), [
            'investment_asset_id' => $asset->id,
            'quantity' => '1.5',
            'total_cost' => '3000000',
            'cost_basis_currency' => Currency::Toman->value,
            'occurred_at' => now()->toDateString(),
        ])
        ->assertRedirect();

    $investment = Investment::query()->sole();

    expect($investment->investment_asset_id)->toBe($asset->id)
        ->and($investment->asset_type)->toBe('nim-half-coin')
        ->and((float) $investment->quantity)->toBe(1.5);
});

test('investment page shows live common and custom asset prices except the selected currency asset', function () {
    Cache::flush();
    config(['services.tgju.enabled' => true]);
    Cache::put('asset-prices.tgju', [
        'gold_750' => 12000000.0,
        'silver' => 200000.0,
        'usd' => 150000.0,
        'eur' => 175500.0,
        'bitcoin' => 15000000000.0,
    ], now()->addMinutes(5));

    $user = User::factory()->withModules()->create();
    InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Nim half coin',
        'unit' => 'coin',
        'color' => '#02CD86',
        'price_source_type' => 'manual',
        'price_source_config' => ['price' => 300000.0],
    ]);

    $this->actingAs($user)
        ->get(route('investments.index', ['currency' => Currency::Usd->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Investments')
            ->loadDeferredProps('default', fn (Assert $page) => $page
                ->where('marketPriceRows', function (mixed $rows): bool {
                    $priceRows = collect($rows);
                    $commonAssetKeys = collect($priceRows->get(0)['assets'] ?? [])->pluck('key');
                    $customAssets = collect($priceRows->get(1)['assets'] ?? []);

                    expect($commonAssetKeys)
                        ->not->toContain('usd')
                        ->toContain('gold')
                        ->toContain('eur')
                        ->toContain('bitcoin')
                        ->and($customAssets->pluck('key'))->toContain('nim-half-coin')
                        ->and($customAssets->firstWhere('key', 'nim-half-coin')['price_formatted'])->toBe('2.00');

                    return true;
                }),
            ),
        );
});

test('selling records a disposal without touching the purchase', function () {
    $user = User::factory()->withModules()->create();
    $asset = InvestmentAsset::query()->where('slug', AssetType::Gold->value)->firstOrFail();

    $purchase = Investment::query()->create([
        'user_id' => $user->id,
        'investment_asset_id' => $asset->id,
        'asset_type' => $asset->slug,
        'kind' => 'buy',
        'quantity' => 5,
        'cost_basis' => 4000000,
        'cost_basis_currency' => Currency::Toman->value,
        'occurred_at' => '2026-07-01',
    ]);

    $this->actingAs($user)
        ->post(route('investments.sell'), [
            'investment_asset_id' => $asset->id,
            'quantity' => '2',
            'total_sale' => '12000000',
            'sale_price_currency' => Currency::Toman->value,
            'occurred_at' => '2026-07-10',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $disposal = Investment::query()->where('kind', 'sell')->sole();

    // Quantity arrives positive and is stored negative, so holdings stay a sum.
    expect((float) $disposal->quantity)->toBe(-2.0)
        // The user enters the total; the per-unit price is derived from it.
        ->and((float) $disposal->sale_price)->toBe(6000000.0)
        // Frozen at the average basis, which is what keeps the remainder honest.
        ->and((float) $disposal->cost_basis)->toBe(4000000.0)
        // The purchase is untouched.
        ->and((float) $purchase->fresh()->quantity)->toBe(5.0);
});

test('a disposal cannot be edited through the purchase route', function () {
    $user = User::factory()->withModules()->create();
    $asset = InvestmentAsset::query()->where('slug', AssetType::Gold->value)->firstOrFail();

    Investment::query()->create([
        'user_id' => $user->id,
        'investment_asset_id' => $asset->id,
        'asset_type' => $asset->slug,
        'kind' => 'buy',
        'quantity' => 5,
        'cost_basis' => 4000000,
        'cost_basis_currency' => Currency::Toman->value,
        'occurred_at' => '2026-07-01',
    ]);

    $this->actingAs($user)
        ->post(route('investments.sell'), [
            'investment_asset_id' => $asset->id,
            'quantity' => '2',
            'total_sale' => '12000000',
            'sale_price_currency' => Currency::Toman->value,
            'occurred_at' => '2026-07-10',
        ])
        ->assertSessionHasNoErrors();

    $disposal = Investment::query()->where('kind', 'sell')->sole();

    // The buy route reads a positive quantity, so letting it write one here
    // turned a two-unit sale into a two-unit purchase and moved the holding by
    // four — while `kind` still said `sell`.
    $this->actingAs($user)
        ->patch(route('investments.update', $disposal), [
            'investment_asset_id' => $asset->id,
            'asset_type' => $asset->slug,
            'quantity' => '3',
            'cost_basis' => '4000000',
            'cost_basis_currency' => Currency::Toman->value,
            'occurred_at' => '2026-07-10',
        ])
        ->assertStatus(409);

    expect((float) $disposal->fresh()->quantity)->toBe(-2.0)
        ->and($user->investments()->get()->sum(fn (Investment $entry): float => (float) $entry->quantity))
        ->toBe(3.0);
});

test('the shared save action refuses a disposal, so every surface is covered', function () {
    // The choke point all four callers share — the web route, the MCP batch, a
    // confirmed MCP proposal and the propose tool. Guarding it here is what
    // stops the next caller from reopening the hole.
    $user = User::factory()->withModules()->create();
    $asset = InvestmentAsset::query()->where('slug', AssetType::Gold->value)->firstOrFail();

    $disposal = Investment::query()->create([
        'user_id' => $user->id,
        'investment_asset_id' => $asset->id,
        'asset_type' => $asset->slug,
        'kind' => 'sell',
        'quantity' => -2,
        'cost_basis' => 4000000,
        'cost_basis_currency' => Currency::Toman->value,
        'sale_price' => 6000000,
        'sale_price_currency' => Currency::Toman->value,
        'occurred_at' => '2026-07-10',
    ]);

    expect(fn () => app(SaveInvestment::class)->update($disposal, ['quantity' => 3]))
        ->toThrow(ValidationException::class);

    expect((float) $disposal->fresh()->quantity)->toBe(-2.0);
});

test('shrinking a purchase below what was already sold is refused', function () {
    $user = User::factory()->withModules()->create();
    $asset = InvestmentAsset::query()->where('slug', AssetType::Gold->value)->firstOrFail();

    $purchase = Investment::query()->create([
        'user_id' => $user->id,
        'investment_asset_id' => $asset->id,
        'asset_type' => $asset->slug,
        'kind' => 'buy',
        'quantity' => 5,
        'cost_basis' => 4000000,
        'cost_basis_currency' => Currency::Toman->value,
        'occurred_at' => '2026-07-01',
    ]);

    $this->actingAs($user)
        ->post(route('investments.sell'), [
            'investment_asset_id' => $asset->id,
            'quantity' => '4',
            'total_sale' => '24000000',
            'sale_price_currency' => Currency::Toman->value,
            'occurred_at' => '2026-07-10',
        ])
        ->assertSessionHasNoErrors();

    // Four are already sold, so a purchase of one leaves the account holding -3 —
    // a position the sell route would never have allowed anyone to reach.
    $this->actingAs($user)
        ->patch(route('investments.update', $purchase), [
            'investment_asset_id' => $asset->id,
            'asset_type' => $asset->slug,
            'quantity' => '1',
            'cost_basis' => '4000000',
            'cost_basis_currency' => Currency::Toman->value,
            'occurred_at' => '2026-07-01',
        ])
        ->assertSessionHasErrors('quantity');

    expect((float) $purchase->fresh()->quantity)->toBe(5.0)
        ->and($user->investments()->get()->sum(fn (Investment $entry): float => (float) $entry->quantity))
        ->toBe(1.0);
});

test('shrinking a purchase to exactly what was sold is still allowed', function () {
    $user = User::factory()->withModules()->create();
    $asset = InvestmentAsset::query()->where('slug', AssetType::Gold->value)->firstOrFail();

    $purchase = Investment::query()->create([
        'user_id' => $user->id,
        'investment_asset_id' => $asset->id,
        'asset_type' => $asset->slug,
        'kind' => 'buy',
        'quantity' => 5,
        'cost_basis' => 4000000,
        'cost_basis_currency' => Currency::Toman->value,
        'occurred_at' => '2026-07-01',
    ]);

    $this->actingAs($user)
        ->post(route('investments.sell'), [
            'investment_asset_id' => $asset->id,
            'quantity' => '4',
            'total_sale' => '24000000',
            'sale_price_currency' => Currency::Toman->value,
            'occurred_at' => '2026-07-10',
        ])
        ->assertSessionHasNoErrors();

    // Landing on exactly zero is a real position, not an overdraft.
    $this->actingAs($user)
        ->patch(route('investments.update', $purchase), [
            'investment_asset_id' => $asset->id,
            'asset_type' => $asset->slug,
            'quantity' => '4',
            'cost_basis' => '4000000',
            'cost_basis_currency' => Currency::Toman->value,
            'occurred_at' => '2026-07-01',
        ])
        ->assertSessionHasNoErrors();

    expect($user->investments()->get()->sum(fn (Investment $entry): float => (float) $entry->quantity))
        ->toBe(0.0);
});

test('moving a purchase to another asset cannot strand the asset it left', function () {
    $user = User::factory()->withModules()->create();
    $gold = InvestmentAsset::query()->where('slug', AssetType::Gold->value)->firstOrFail();
    $silver = InvestmentAsset::query()->where('slug', AssetType::Silver->value)->firstOrFail();

    $purchase = Investment::query()->create([
        'user_id' => $user->id,
        'investment_asset_id' => $gold->id,
        'asset_type' => $gold->slug,
        'kind' => 'buy',
        'quantity' => 5,
        'cost_basis' => 4000000,
        'cost_basis_currency' => Currency::Toman->value,
        'occurred_at' => '2026-07-01',
    ]);

    $this->actingAs($user)
        ->post(route('investments.sell'), [
            'investment_asset_id' => $gold->id,
            'quantity' => '4',
            'total_sale' => '24000000',
            'sale_price_currency' => Currency::Toman->value,
            'occurred_at' => '2026-07-10',
        ])
        ->assertSessionHasNoErrors();

    // Carrying the purchase over to silver would leave the gold sale standing
    // alone against nothing.
    $this->actingAs($user)
        ->patch(route('investments.update', $purchase), [
            'investment_asset_id' => $silver->id,
            'asset_type' => $silver->slug,
            'quantity' => '5',
            'cost_basis' => '4000000',
            'cost_basis_currency' => Currency::Toman->value,
            'occurred_at' => '2026-07-01',
        ])
        ->assertSessionHasErrors('quantity');

    expect((int) $purchase->fresh()->investment_asset_id)->toBe((int) $gold->id);
});

test('the entry list marks disposals so the purchase dialog is never offered for one', function () {
    $user = User::factory()->withModules()->create();
    $asset = InvestmentAsset::query()->where('slug', AssetType::Gold->value)->firstOrFail();

    Investment::query()->create([
        'user_id' => $user->id,
        'investment_asset_id' => $asset->id,
        'asset_type' => $asset->slug,
        'kind' => 'buy',
        'quantity' => 5,
        'cost_basis' => 4000000,
        'cost_basis_currency' => Currency::Toman->value,
        'occurred_at' => '2026-07-01',
    ]);

    $this->actingAs($user)
        ->post(route('investments.sell'), [
            'investment_asset_id' => $asset->id,
            'quantity' => '2',
            'total_sale' => '12000000',
            'sale_price_currency' => Currency::Toman->value,
            'occurred_at' => '2026-07-10',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get(route('investments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Investments')
            ->where('entries', function (mixed $entries): bool {
                $byKind = collect($entries)->groupBy(fn (array $entry): string => $entry['kind']);

                expect($byKind->get('sell'))->toHaveCount(1)
                    ->and($byKind->get('buy'))->toHaveCount(1);

                return true;
            }),
        );
});

test('you cannot sell more than you hold', function () {
    $user = User::factory()->withModules()->create();
    $asset = InvestmentAsset::query()->where('slug', AssetType::Gold->value)->firstOrFail();

    Investment::query()->create([
        'user_id' => $user->id,
        'investment_asset_id' => $asset->id,
        'asset_type' => $asset->slug,
        'kind' => 'buy',
        'quantity' => 1,
        'cost_basis' => 4000000,
        'cost_basis_currency' => Currency::Toman->value,
        'occurred_at' => '2026-07-01',
    ]);

    $this->actingAs($user)
        ->post(route('investments.sell'), [
            'investment_asset_id' => $asset->id,
            'quantity' => '3',
            'total_sale' => '18000000',
            'sale_price_currency' => Currency::Toman->value,
            'occurred_at' => '2026-07-10',
        ])
        ->assertSessionHasErrors('quantity');

    expect(Investment::query()->where('kind', 'sell')->count())->toBe(0);
});

test('you can sell the whole holding when its float sum drifts just below it', function () {
    $user = User::factory()->withModules()->create();
    $asset = InvestmentAsset::query()->where('slug', AssetType::Gold->value)->firstOrFail();

    // 0.2 + 0.7 + 0.1 sums to 0.9999999999999999 in floating point.
    foreach (['0.2', '0.7', '0.1'] as $quantity) {
        Investment::query()->create([
            'user_id' => $user->id,
            'investment_asset_id' => $asset->id,
            'asset_type' => $asset->slug,
            'kind' => 'buy',
            'quantity' => $quantity,
            'cost_basis' => 4000000,
            'cost_basis_currency' => Currency::Toman->value,
            'occurred_at' => '2026-07-01',
        ]);
    }

    $this->actingAs($user)
        ->post(route('investments.sell'), [
            'investment_asset_id' => $asset->id,
            'quantity' => '1',
            'total_sale' => '18000000',
            'sale_price_currency' => Currency::Toman->value,
            'occurred_at' => '2026-07-10',
        ])
        ->assertSessionHasNoErrors();

    expect((float) Investment::query()->where('kind', 'sell')->sole()->quantity)->toBe(-1.0);
});

test('buying can mirror itself into a cost transaction', function () {
    $user = User::factory()->withModules()->create();
    $asset = InvestmentAsset::query()->where('slug', AssetType::Gold->value)->firstOrFail();

    $this->actingAs($user)
        ->post(route('investments.store'), [
            'investment_asset_id' => $asset->id,
            'quantity' => '1',
            'cost_basis' => '17000000',
            'cost_basis_currency' => Currency::Toman->value,
            'occurred_at' => '2026-07-10',
            'record_transaction' => true,
        ])
        ->assertRedirect();

    $transaction = Transaction::query()->sole();
    $investmentCategory = Category::query()
        ->whereNull('user_id')
        ->where('type', 'cost')
        ->where('slug', 'investment')
        ->sole();

    // Quantity x price, on the investment's own date, under Investment — which is
    // exactly what the report's "exclude investments" filter keys off.
    expect($transaction->type->value)->toBe('cost')
        ->and((float) $transaction->amount)->toBe(17000000.0)
        ->and($transaction->occurred_at->toDateString())->toBe('2026-07-10')
        ->and($transaction->category_id)->toBe($investmentCategory->id)
        // The title is shared with the Vue dialogs, so it uses vue-i18n's {param}
        // syntax and this side fills it in by hand. Asserting the finished string
        // is what catches a placeholder shipping unreplaced.
        ->and((string) $transaction->title)->toContain('Gold')
        ->and((string) $transaction->title)->toContain('1')
        ->and((string) $transaction->title)->not->toContain('{')
        ->and((string) $transaction->title)->not->toContain(':quantity');
});

test('buying records no transaction unless asked', function () {
    $user = User::factory()->withModules()->create();
    $asset = InvestmentAsset::query()->where('slug', AssetType::Gold->value)->firstOrFail();

    $this->actingAs($user)
        ->post(route('investments.store'), [
            'investment_asset_id' => $asset->id,
            'quantity' => '1',
            'cost_basis' => '17000000',
            'cost_basis_currency' => Currency::Toman->value,
            'occurred_at' => '2026-07-10',
        ])
        ->assertRedirect();

    expect(Transaction::query()->count())->toBe(0);
});

test('selling can mirror the full proceeds into income, not just the profit', function () {
    $user = User::factory()->withModules()->create();
    $asset = InvestmentAsset::query()->where('slug', AssetType::Gold->value)->firstOrFail();

    Investment::query()->create([
        'user_id' => $user->id,
        'investment_asset_id' => $asset->id,
        'asset_type' => $asset->slug,
        'kind' => 'buy',
        'quantity' => 2,
        'cost_basis' => 4000000,
        'cost_basis_currency' => Currency::Toman->value,
        'occurred_at' => '2026-07-01',
    ]);

    $this->actingAs($user)
        ->post(route('investments.sell'), [
            'investment_asset_id' => $asset->id,
            'quantity' => '2',
            'total_sale' => '12000000',
            'sale_price_currency' => Currency::Toman->value,
            'occurred_at' => '2026-07-10',
            'record_transaction' => true,
        ])
        ->assertRedirect();

    // 2 x 6,000,000 = the money that actually arrived. Recording only the
    // 4,000,000 profit would describe a payment that never happened.
    expect((float) Transaction::query()->sole()->amount)->toBe(12000000.0);
});

/*
 * The entries table used to price every row at today's rate, so two disposals
 * of the same weight on consecutive days read identically even though one made
 * more than the other. What a row moved is frozen on the row itself.
 */
test('an entry carries the proceeds a disposal actually made', function () {
    $user = User::factory()->withModules()->create();
    $asset = InvestmentAsset::query()->where('slug', AssetType::Gold->value)->sole();

    $this->actingAs($user)->post(route('investments.store'), [
        'asset_type' => AssetType::Gold->value,
        'quantity' => '2',
        'total_cost' => '1000000',
        'cost_basis_currency' => Currency::Toman->value,
        'occurred_at' => now()->subDay()->toDateString(),
    ])->assertRedirect();

    $this->actingAs($user)->post(route('investments.sell'), [
        'investment_asset_id' => $asset->id,
        'quantity' => '1',
        'total_sale' => '900000',
        'sale_price_currency' => Currency::Toman->value,
        'occurred_at' => now()->toDateString(),
    ])->assertRedirect();

    $this->actingAs($user)->get(route('investments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries', 2)
            ->where('entries', function ($entries): bool {
                $rows = collect($entries)->keyBy('kind');

                // Per unit, as stored — the page multiplies by the quantity.
                return (float) $rows['sell']['sale_price'] === 900000.0
                    && $rows['sell']['sale_price_currency'] === Currency::Toman->value
                    // A purchase has no proceeds, and must not borrow the sale's.
                    && $rows['buy']['sale_price'] === null;
            })
            ->etc());
});
