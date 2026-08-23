<?php

use App\Actions\Billing\StartSubscriptionPayment;
use App\Enums\BillingPlan;
use App\Enums\DepositAddressStatus;
use App\Enums\PaymentNetwork;
use App\Enums\SettlementAsset;
use App\Exceptions\DepositAddressLimitExceeded;
use App\Exceptions\DepositAddressUnavailable;
use App\Jobs\ReconcileSubscriptionsJob;
use App\Models\DepositAddress;
use App\Models\User;
use App\Support\Billing\BillingCatalog;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    enableBilling();
});

function depositCsv(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'deposit-pool-');
    file_put_contents($path, $contents);

    return $path;
}

test('offline csv import validates normalizes and imports atomically', function () {
    DepositAddress::query()->delete();

    $valid = depositCsv("network,derivation_index,address\nethereum,4,0xAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA\narbitrum,4,0xBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB\n");

    $exitCode = Artisan::call('billing:import-deposit-addresses', ['file' => $valid]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Imported 2 deposit address(es).');

    expect(DepositAddress::query()->count())->toBe(2)
        ->and(DepositAddress::query()->where('network', 'ethereum')->sole()->address)
        ->toBe('0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');

    $conflict = depositCsv("network,derivation_index,address\nethereum,5,0xCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCC\nethereum,4,0xDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDD\n");
    $before = DepositAddress::query()->count();

    $this->artisan('billing:import-deposit-addresses', ['file' => $conflict])->assertFailed();

    expect(DepositAddress::query()->count())->toBe($before);

    unlink($valid);
    unlink($conflict);
});

test('csv import rejects secret columns and duplicate indexes or addresses', function () {
    DepositAddress::query()->delete();

    $secret = depositCsv("network,derivation_index,address,private_key\nethereum,1,0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa,secret\n");
    $duplicate = depositCsv("network,derivation_index,address\nethereum,1,0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa\nethereum,1,0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb\n");

    $this->artisan('billing:import-deposit-addresses', ['file' => $secret])->assertFailed();
    $this->artisan('billing:import-deposit-addresses', ['file' => $duplicate])->assertFailed();

    expect(DepositAddress::query()->count())->toBe(0);

    unlink($secret);
    unlink($duplicate);
});

test('each intent atomically consumes a distinct address and equivalent retries consume none', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $start = app(StartSubscriptionPayment::class);

    $first = $start($firstUser, BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Usdt);
    $same = $start($firstUser, BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Usdt);
    $second = $start($secondUser, BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Usdt);

    expect($same->is($first))->toBeTrue()
        ->and($first->pay_to_address)->not->toBe($second->pay_to_address)
        ->and(DepositAddress::query()->where('status', DepositAddressStatus::Assigned->value)->count())->toBe(2)
        ->and(DepositAddress::query()->where('assigned_payment_id', $first->id)->count())->toBe(1);
});

test('cancelled and expired addresses retire permanently and are never reused', function () {
    $user = User::factory()->create();
    $start = app(StartSubscriptionPayment::class);

    $cancelled = $start($user, BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Usdt);
    $this->actingAs($user)->delete(route('billing.payments.cancel', $cancelled))->assertRedirect();

    expect($cancelled->depositAddress->fresh()->status)->toBe(DepositAddressStatus::Retired);

    $expiring = $start($user, BillingPlan::Quarterly, PaymentNetwork::Ethereum, SettlementAsset::Usdt);
    $expiring->forceFill(['expires_at' => now()->subMinute()])->save();
    app(ReconcileSubscriptionsJob::class)->handle();

    $new = $start($user, BillingPlan::Yearly, PaymentNetwork::Ethereum, SettlementAsset::Usdt);

    expect($expiring->depositAddress->fresh()->status)->toBe(DepositAddressStatus::Retired)
        ->and($new->pay_to_address)->not->toBe($cancelled->pay_to_address)
        ->and($new->pay_to_address)->not->toBe($expiring->pay_to_address);
});

test('daily allocation limit counts only new addresses', function () {
    config()->set('billing.deposit_pool.max_assignments_per_user_per_day', 2);
    $user = User::factory()->create();
    $start = app(StartSubscriptionPayment::class);

    $first = $start($user, BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Usdt);
    $same = $start($user, BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Usdt);
    $start($user, BillingPlan::Quarterly, PaymentNetwork::Ethereum, SettlementAsset::Usdt);

    expect($same->is($first))->toBeTrue();

    expect(fn () => $start(
        $user,
        BillingPlan::Yearly,
        PaymentNetwork::Ethereum,
        SettlementAsset::Usdt,
    ))->toThrow(DepositAddressLimitExceeded::class);
});

test('an empty pool fails closed and disables that network in the catalog', function () {
    DepositAddress::query()->delete();

    expect(fn () => app(StartSubscriptionPayment::class)(
        User::factory()->create(),
        BillingPlan::Monthly,
        PaymentNetwork::Ethereum,
        SettlementAsset::Usdt,
    ))->toThrow(DepositAddressUnavailable::class);

    $network = collect(app(BillingCatalog::class)->networks())->firstWhere('key', 'ethereum');

    expect($network['available'])->toBeFalse()
        ->and($network['available_addresses'])->toBe(0);
});

test('asset availability is eth only on ethereum and eth usdt usdc on arbitrum', function () {
    config()->set([
        'billing.networks.ethereum.assets.usdt.enabled' => false,
        'billing.networks.ethereum.assets.usdc.enabled' => false,
        'billing.networks.arbitrum.enabled' => true,
        'billing.networks.arbitrum.rpc_url' => 'https://arbitrum.test/rpc',
        'billing.networks.arbitrum.assets.eth.enabled' => true,
        'billing.networks.arbitrum.assets.usdt.enabled' => true,
        'billing.networks.arbitrum.assets.usdc.enabled' => true,
    ]);

    expect(array_map(fn (SettlementAsset $asset) => $asset->value, PaymentNetwork::Ethereum->assets()))
        ->toBe(['eth'])
        ->and(array_map(fn (SettlementAsset $asset) => $asset->value, PaymentNetwork::Arbitrum->assets()))
        ->toBe(['eth', 'usdt', 'usdc']);
});
