<?php

use App\Actions\Billing\StartSubscriptionPayment;
use App\Actions\Billing\SubmitPaymentProof;
use App\Enums\BillingPlan;
use App\Enums\DepositAddressStatus;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentNetwork;
use App\Enums\PaymentStatus;
use App\Enums\SettlementAsset;
use App\Exceptions\DepositAddressLimitExceeded;
use App\Exceptions\DepositAddressUnavailable;
use App\Jobs\ReconcileSubscriptionsJob;
use App\Jobs\RefillDepositAddressPoolJob;
use App\Models\DepositAddress;
use App\Models\User;
use App\Models\WalletDerivationState;
use App\Services\Billing\WalletSignerClient;
use App\Support\Billing\BillingCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

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

    $valid = depositCsv('derivation_index,address
4,0xAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
5,0xBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB
');

    $exitCode = Artisan::call('billing:import-deposit-addresses', ['file' => $valid]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Imported 2 deposit address(es).');

    // Imported without a network, the same shape the signer's own batches take:
    // one key controls the address on every chain, and the payment that claims
    // it decides which chain that turns out to be.
    expect(DepositAddress::query()->count())->toBe(2)
        ->and(DepositAddress::query()->whereNotNull('network')->count())->toBe(0)
        ->and(DepositAddress::query()->where('derivation_index', 4)->sole()->address)
        ->toBe('0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');

    $conflict = depositCsv('derivation_index,address
6,0xCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCC
4,0xDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDD
');
    $before = DepositAddress::query()->count();

    $this->artisan('billing:import-deposit-addresses', ['file' => $conflict])->assertFailed();

    expect(DepositAddress::query()->count())->toBe($before);

    unlink($valid);
    unlink($conflict);
});

test('csv import rejects secret columns and duplicate indexes or addresses', function () {
    DepositAddress::query()->delete();

    $secret = depositCsv('derivation_index,address,private_key
1,0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa,secret
');
    $duplicateIndex = depositCsv('derivation_index,address
1,0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
1,0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb
');
    $duplicateAddress = depositCsv('derivation_index,address
1,0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
2,0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
');

    $this->artisan('billing:import-deposit-addresses', ['file' => $secret])->assertFailed();
    $this->artisan('billing:import-deposit-addresses', ['file' => $duplicateIndex])->assertFailed();
    $this->artisan('billing:import-deposit-addresses', ['file' => $duplicateAddress])->assertFailed();

    expect(DepositAddress::query()->count())->toBe(0);

    unlink($secret);
    unlink($duplicateIndex);
    unlink($duplicateAddress);
});

test('the schema refuses a reused address or derivation index outright', function () {
    DepositAddress::query()->delete();

    DepositAddress::create([
        'key_version' => 'v1',
        'network' => null,
        'derivation_index' => 7,
        'address' => '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'status' => DepositAddressStatus::Available,
    ]);

    // A pooled row carries a null network, and MySQL does not treat two NULLs
    // as equal — so a uniqueness rule that mentioned network constrained
    // nothing at all here. These two inserts are the regression.
    expect(fn () => DepositAddress::create([
        'key_version' => 'v1',
        'network' => PaymentNetwork::Arbitrum,
        'derivation_index' => 8,
        'address' => '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'status' => DepositAddressStatus::Available,
    ]))->toThrow(QueryException::class);

    expect(fn () => DepositAddress::create([
        'key_version' => 'v1',
        'network' => PaymentNetwork::Arbitrum,
        'derivation_index' => 7,
        'address' => '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        'status' => DepositAddressStatus::Available,
    ]))->toThrow(QueryException::class);

    expect(DepositAddress::query()->count())->toBe(1);
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

test('proof submission re-reads the payment after waiting for its row lock', function () {
    Queue::fake();
    $user = User::factory()->create();
    $payment = app(StartSubscriptionPayment::class)(
        $user,
        BillingPlan::Monthly,
        PaymentNetwork::Ethereum,
        SettlementAsset::Usdt,
    );
    $stalePayment = $payment->fresh();

    $payment->forceFill([
        'status' => PaymentStatus::Expired,
        'failure_reason' => PaymentFailureReason::Expired,
    ])->save();
    $payment->depositAddress->forceFill(['status' => DepositAddressStatus::Retired])->save();

    $result = app(SubmitPaymentProof::class)(
        $stalePayment,
        '0x'.str_repeat('a', 64),
    );

    expect($result->accepted)->toBeFalse()
        ->and($result->reason)->toBe(PaymentFailureReason::Expired)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($payment->depositAddress->fresh()->status)->toBe(DepositAddressStatus::Retired);

    Queue::assertNothingPushed();
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

test('the refill picks up where an offline import left off instead of wedging', function () {
    // The deadlock this closes: next_deposit_index was seeded once, when its
    // row was created, so a CSV import afterwards left it pointing at indices
    // the table already held. The job asked the signer to re-derive an address
    // it had already handed out, threw on the duplicate, and rolled back the
    // counter it never advanced — repeating identically once a minute for ever
    // while the pool drained to empty and checkout stopped.
    DepositAddress::query()->delete();
    config()->set([
        'billing.deposit_pool.target' => 12,
        'billing.signer.secret_file' => $secret = tempnam(sys_get_temp_dir(), 'signer-secret-'),
    ]);
    file_put_contents($secret, str_repeat('a', 64));

    WalletDerivationState::query()->create(['key_version' => 'v1', 'next_deposit_index' => 5]);
    Artisan::call('billing:import-deposit-addresses', ['file' => depositCsv(
        "derivation_index,address\n"
        ."5,0x0000000000000000000000000000000000000005\n"
        ."6,0x0000000000000000000000000000000000000006\n"
        ."7,0x0000000000000000000000000000000000000007\n"
    )]);

    $requested = null;
    Http::fake(function (Request $request) use (&$requested) {
        $requested = $request->data();

        return Http::response([
            'key_version' => 'v1',
            'start_index' => $requested['startIndex'],
            'addresses' => collect(range(0, $requested['count'] - 1))
                ->map(fn (int $offset): array => [
                    'index' => $requested['startIndex'] + $offset,
                    'address' => '0x'.str_pad(dechex(0xAA00 + $requested['startIndex'] + $offset), 40, '0', STR_PAD_LEFT),
                ])->all(),
        ]);
    });

    (new RefillDepositAddressPoolJob)->handle(app(WalletSignerClient::class));

    // 8, not 5: the three imported rows are already handed out.
    expect($requested['startIndex'])->toBe(8)
        ->and($requested['count'])->toBe(9)
        ->and(DepositAddress::query()->count())->toBe(12)
        ->and(WalletDerivationState::query()->first()->next_deposit_index)->toBe(17);

    unlink($secret);
});

test('the refill never re-derives an index that was handed out and later deleted', function () {
    // The counter still wins when it is ahead of the table. An address that was
    // assigned and then purged must not come back round a second time.
    DepositAddress::query()->delete();
    config()->set([
        'billing.deposit_pool.target' => 2,
        'billing.signer.secret_file' => $secret = tempnam(sys_get_temp_dir(), 'signer-secret-'),
    ]);
    file_put_contents($secret, str_repeat('a', 64));

    WalletDerivationState::query()->create(['key_version' => 'v1', 'next_deposit_index' => 40]);

    $requested = null;
    Http::fake(function (Request $request) use (&$requested) {
        $requested = $request->data();

        return Http::response([
            'key_version' => 'v1',
            'addresses' => collect(range(0, $requested['count'] - 1))
                ->map(fn (int $offset): array => [
                    'index' => $requested['startIndex'] + $offset,
                    'address' => '0x'.str_pad(dechex(0xBB00 + $offset), 40, '0', STR_PAD_LEFT),
                ])->all(),
        ]);
    });

    (new RefillDepositAddressPoolJob)->handle(app(WalletSignerClient::class));

    expect($requested['startIndex'])->toBe(40)
        ->and(WalletDerivationState::query()->first()->next_deposit_index)->toBe(42);

    unlink($secret);
});

test('a derivation index outside the requested range is refused', function () {
    // The bounds check protects the counter's whole meaning: an index stored
    // from outside the range would leave the next run overlapping it.
    DepositAddress::query()->delete();
    config()->set([
        'billing.deposit_pool.target' => 2,
        'billing.signer.secret_file' => $secret = tempnam(sys_get_temp_dir(), 'signer-secret-'),
    ]);
    file_put_contents($secret, str_repeat('a', 64));

    Http::fake(fn () => Http::response([
        'key_version' => 'v1',
        'addresses' => [
            ['index' => 0, 'address' => '0x'.str_pad('1', 40, '0', STR_PAD_LEFT)],
            ['index' => 999, 'address' => '0x'.str_pad('2', 40, '0', STR_PAD_LEFT)],
        ],
    ]));

    expect(fn () => (new RefillDepositAddressPoolJob)->handle(app(WalletSignerClient::class)))
        ->toThrow(RuntimeException::class, 'outside the requested range');

    expect(DepositAddress::query()->count())->toBe(0);

    unlink($secret);
});

test('an address taken mid-claim hands the buyer the next one instead of failing', function () {
    // The regression this pins: allocation used an ordered LIMIT 1 with
    // lockForUpdate, so simultaneous buyers all queued behind the same
    // lowest-index row. When the winner committed, the losers re-evaluated that
    // one row, found it no longer available, and came back with nothing — so a
    // buyer was told the pool was empty while the rest of it sat unused.
    DepositAddress::query()->delete();
    DepositAddress::query()->create([
        'derivation_index' => 0,
        'address' => '0x00000000000000000000000000000000000000aa',
        'status' => DepositAddressStatus::Available,
    ]);
    DepositAddress::query()->create([
        'derivation_index' => 1,
        'address' => '0x00000000000000000000000000000000000000bb',
        'status' => DepositAddressStatus::Available,
    ]);

    // Fires between the select that picks a candidate and the update that
    // claims it — exactly the window a competing buyer commits in.
    $stolen = false;
    Event::listen(
        'eloquent.retrieved: '.DepositAddress::class,
        function (DepositAddress $address) use (&$stolen): void {
            if ($stolen || $address->status !== DepositAddressStatus::Available) {
                return;
            }

            $stolen = true;
            DepositAddress::query()->whereKey($address->getKey())
                ->update(['status' => DepositAddressStatus::Assigned->value]);
        },
    );

    $payment = app(StartSubscriptionPayment::class)(
        User::factory()->create(),
        BillingPlan::Monthly,
        PaymentNetwork::Ethereum,
        SettlementAsset::Usdt,
    );

    expect($stolen)->toBeTrue()
        // The second address, because the first was gone by the time it claimed.
        ->and($payment->pay_to_address)->toBe('0x00000000000000000000000000000000000000bb')
        ->and($payment->depositAddress->derivation_index)->toBe(1)
        ->and($payment->depositAddress->assigned_payment_id)->toBe($payment->getKey());
});

test('a genuinely empty pool is still refused rather than looped over', function () {
    // The claim loop must not turn "nothing left" into a spin: an exhausted
    // pool has to fail closed on the first pass.
    DepositAddress::query()->delete();

    $selects = 0;
    Event::listen('eloquent.retrieved: '.DepositAddress::class, function () use (&$selects): void {
        $selects++;
    });

    expect(fn () => app(StartSubscriptionPayment::class)(
        User::factory()->create(),
        BillingPlan::Monthly,
        PaymentNetwork::Ethereum,
        SettlementAsset::Usdt,
    ))->toThrow(DepositAddressUnavailable::class);

    expect($selects)->toBe(0);
});

test('the catalog counts the shared pool once for every chain', function () {
    enableBilling([
        'billing.networks.arbitrum.enabled' => true,
        'billing.networks.arbitrum.rpc_url' => 'https://arbitrum.test/rpc',
        'billing.networks.arbitrum.rpc_urls' => ['https://arbitrum.test/rpc'],
    ]);
    DepositAddress::query()->delete();

    foreach ([
        DepositAddressStatus::Available,
        DepositAddressStatus::Available,
        DepositAddressStatus::Available,
        DepositAddressStatus::Assigned,
        DepositAddressStatus::Retired,
        DepositAddressStatus::Swept,
    ] as $index => $status) {
        DepositAddress::query()->create([
            'derivation_index' => $index,
            'address' => '0x'.str_pad(dechex(0xC0 + $index), 40, '0', STR_PAD_LEFT),
            'status' => $status,
        ]);
    }

    $poolQueries = 0;
    DB::listen(function ($query) use (&$poolQueries): void {
        if (str_contains($query->sql, 'deposit_addresses')) {
            $poolQueries++;
        }
    });

    $networks = collect(app(BillingCatalog::class)->networks());

    expect($networks->pluck('key')->all())->toBe(['ethereum', 'arbitrum'])
        // Only the three available ones, and the same three for both chains: an
        // address is derived with no network and belongs to one only once a
        // payment claims it, at which point it is no longer available.
        ->and($networks->pluck('available_addresses')->all())->toBe([3, 3])
        ->and($networks->pluck('available')->all())->toBe([true, true])
        // One count serves every chain, rather than one query per chain.
        ->and($poolQueries)->toBe(1);
});
