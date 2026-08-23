<?php

use App\Actions\Billing\StartSubscriptionPayment;
use App\Enums\BillingPlan;
use App\Enums\PaymentNetwork;
use App\Enums\PaymentStatus;
use App\Enums\SettlementAsset;
use App\Exceptions\QuoteUnavailable;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\Billing\TokenAmount;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    enableBilling();
});

test('an intent snapshots every term the buyer was shown', function () {
    $user = User::factory()->create();

    $payment = app(StartSubscriptionPayment::class)(
        $user, BillingPlan::Yearly, PaymentNetwork::Ethereum, SettlementAsset::Usdt
    );

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->plan)->toBe(BillingPlan::Yearly)
        ->and($payment->months)->toBe(12)
        ->and($payment->price_usd)->toBe('45.00')
        ->and($payment->network)->toBe(PaymentNetwork::Ethereum)
        ->and($payment->chain_id)->toBe(1)
        ->and($payment->asset)->toBe(SettlementAsset::Usdt)
        ->and($payment->asset_decimals)->toBe(6)
        ->and($payment->token_contract)->toBe('0xdac17f958d2ee523a2206206994597c13d831ec7')
        ->and($payment->pay_to_address)->toBe(mb_strtolower(TEST_RECEIVING_ADDRESS))
        ->and($payment->quote_rate)->toBe('1.00000000')
        ->and($payment->expires_at->isFuture())->toBeTrue();
});

test('a stablecoin uses the exact quoted price and needs no rate lookup', function () {
    Http::fake();
    $user = User::factory()->create();

    $payment = app(StartSubscriptionPayment::class)(
        $user, BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Usdt
    );

    expect($payment->expected_amount)->toBe('5.00')
        ->and(TokenAmount::fromDecimal($payment->expected_amount, 6))->toBe('5000000');

    Http::assertNothingSent();
});

test('open intents use distinct addresses even when their exact amount is the same', function () {
    $payments = collect(range(1, 25))->map(function () {
        return app(StartSubscriptionPayment::class)(
            User::factory()->create(),
            BillingPlan::Monthly,
            PaymentNetwork::Ethereum,
            SettlementAsset::Usdt,
        );
    });

    expect($payments->pluck('pay_to_address')->unique()->count())->toBe(25)
        ->and($payments->pluck('expected_amount')->unique()->values()->all())->toBe(['5.00']);
});

test('a volatile asset is priced from a live rate and locked to a short window', function () {
    config()->set('billing.quote.enabled', true);
    Http::fake(['*' => Http::response(['ethereum' => ['usd' => 2000.0]])]);

    $payment = app(StartSubscriptionPayment::class)(
        User::factory()->create(), BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Eth
    );

    expect($payment->quote_rate)->toBe('2000.00000000')
        ->and($payment->asset_decimals)->toBe(18)
        ->and($payment->token_contract)->toBeNull()
        ->and($payment->expected_amount)->toBe('0.00250000')
        // A volatile quote is honoured for far less time than the intent lives,
        // because we carry the price risk for its whole duration.
        ->and($payment->quote_expires_at->lessThan($payment->expires_at))->toBeTrue();
});

test('an unavailable rate refuses the sale instead of pricing it at nothing', function () {
    config()->set('billing.quote.enabled', true);
    Http::fake(['*' => Http::response(null, 503)]);

    expect(fn () => app(StartSubscriptionPayment::class)(
        User::factory()->create(), BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Eth
    ))->toThrow(QuoteUnavailable::class);

    expect(SubscriptionPayment::query()->count())->toBe(0);
});

test('amounts survive the database unchanged, to the last digit', function () {
    config()->set('billing.quote.enabled', true);
    Http::fake(['*' => Http::response(['ethereum' => ['usd' => 2000.0]])]);

    $payment = app(StartSubscriptionPayment::class)(
        User::factory()->create(), BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Eth
    );

    $reloaded = $payment->fresh();

    // Native ether is where this bites: eighteen decimals is past what a float
    // holds, and a DECIMAL column read back as one would quietly round away the
    // nonce digits — the exact digits that make the amount unique.
    expect($reloaded->expected_amount)->toBeString()
        ->and($reloaded->expected_amount)->toBe($payment->expected_amount)
        ->and($reloaded->expectedBaseUnits())->toBe($payment->expectedBaseUnits())
        ->and($reloaded->price_usd)->toBe('5.00')
        ->and($reloaded->quote_rate)->toBe('2000.00000000');
});

test('asking twice returns the same open intent rather than a second one', function () {
    $user = User::factory()->create();

    $first = app(StartSubscriptionPayment::class)(
        $user, BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Usdt
    );
    $second = app(StartSubscriptionPayment::class)(
        $user, BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Usdt
    );

    // Same amount too — the buyer may already have copied it into a wallet.
    expect($second->id)->toBe($first->id)
        ->and($second->expected_amount)->toBe($first->expected_amount)
        ->and(SubscriptionPayment::query()->count())->toBe(1);
});

test('a different plan or asset gets its own intent', function () {
    $user = User::factory()->create();

    app(StartSubscriptionPayment::class)($user, BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Usdt);
    app(StartSubscriptionPayment::class)($user, BillingPlan::Yearly, PaymentNetwork::Ethereum, SettlementAsset::Usdt);
    app(StartSubscriptionPayment::class)($user, BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Usdc);

    expect(SubscriptionPayment::query()->count())->toBe(3);
});

test('an expired intent does not block a fresh one', function () {
    $user = User::factory()->create();
    SubscriptionPayment::factory()->stale()->create(['user_id' => $user->id]);

    $fresh = app(StartSubscriptionPayment::class)(
        $user, BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Usdt
    );

    expect($fresh->isOpen())->toBeTrue()
        ->and(SubscriptionPayment::query()->count())->toBe(2);
});

test('the action refuses rails the config does not offer', function () {
    $user = User::factory()->create();

    config()->set('billing.enabled', false);
    expect(fn () => app(StartSubscriptionPayment::class)(
        $user, BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Usdt
    ))->toThrow(RuntimeException::class);

    enableBilling(['billing.networks.ethereum.enabled' => false]);
    expect(fn () => app(StartSubscriptionPayment::class)(
        $user, BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Usdt
    ))->toThrow(RuntimeException::class);

    enableBilling(['billing.networks.ethereum.assets.usdc.contract' => null]);
    expect(fn () => app(StartSubscriptionPayment::class)(
        $user, BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Usdc
    ))->toThrow(RuntimeException::class);

    enableBilling(['billing.plans.monthly.price_usd' => '0.00']);
    expect(fn () => app(StartSubscriptionPayment::class)(
        $user, BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Usdt
    ))->toThrow(RuntimeException::class);

    expect(SubscriptionPayment::query()->count())->toBe(0);
});

test('a network with no endpoint is not offered and shared address config is irrelevant', function () {
    enableBilling([
        'billing.networks.ethereum.rpc_url' => null,
        'billing.networks.ethereum.rpc_urls' => [],
    ]);
    expect(PaymentNetwork::available())->toBe([]);

    enableBilling();
    expect(PaymentNetwork::available())->toBe([PaymentNetwork::Ethereum])
        ->and(PaymentNetwork::Ethereum->assets())
        ->toBe([SettlementAsset::Eth, SettlementAsset::Usdt, SettlementAsset::Usdc]);
});
