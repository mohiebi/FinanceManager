<?php

use App\Actions\Billing\GrantProAccess;
use App\Actions\Billing\StartSubscriptionPayment;
use App\Actions\Billing\VerifyPaymentOnChain;
use App\Enums\BillingPlan;
use App\Enums\PaymentNetwork;
use App\Enums\PaymentStatus;
use App\Enums\SettlementAsset;
use App\Jobs\VerifySubscriptionPaymentJob;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\Billing\TokenAmount;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

const ARBITRUM_USDC = '0xaf88d065e77c8cc2239327c5edb3a432268e5831';

/**
 * Both chains payable, which is the configuration this file exists to exercise.
 */
function enableBothChains(array $overrides = []): void
{
    enableBilling([
        'billing.networks.arbitrum.enabled' => true,
        'billing.networks.arbitrum.address' => TEST_RECEIVING_ADDRESS,
        'billing.networks.arbitrum.rpc_url' => 'https://arbitrum.test/rpc',
        ...$overrides,
    ]);
}

beforeEach(function () {
    enableBothChains();
    Notification::fake();
});

test('a second chain needs no code beyond its enum case and config', function () {
    expect(PaymentNetwork::available())
        ->toBe([PaymentNetwork::Ethereum, PaymentNetwork::Arbitrum])
        ->and(PaymentNetwork::Arbitrum->chainId())->toBe(42161)
        ->and(PaymentNetwork::Arbitrum->nativeAsset())->toBe(SettlementAsset::Eth)
        ->and(PaymentNetwork::Arbitrum->assets())
        ->toBe([SettlementAsset::Eth, SettlementAsset::Usdt, SettlementAsset::Usdc]);
});

test('a rollup says so, because its confirmations mean something different', function () {
    // Twenty Arbitrum blocks is about five seconds and means the sequencer
    // accepted the transaction — not that it has settled on Ethereum.
    expect(PaymentNetwork::Arbitrum->isRollup())->toBeTrue()
        ->and(PaymentNetwork::Ethereum->isRollup())->toBeFalse();
});

test('an Arbitrum intent snapshots that chain, not the mainnet one', function () {
    $payment = app(StartSubscriptionPayment::class)(
        User::factory()->create(),
        BillingPlan::Monthly,
        PaymentNetwork::Arbitrum,
        SettlementAsset::Usdc,
    );

    expect($payment->network)->toBe(PaymentNetwork::Arbitrum)
        ->and($payment->chain_id)->toBe(42161)
        // A different contract from the Ethereum USDC, which is the whole reason
        // decimals and contracts are read per network rather than per asset.
        ->and($payment->token_contract)->toBe(ARBITRUM_USDC)
        ->and($payment->asset_decimals)->toBe(6);
});

test('the two chains never share an expected amount', function () {
    $onMainnet = app(StartSubscriptionPayment::class)(
        User::factory()->create(), BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Usdc
    );
    $onArbitrum = app(StartSubscriptionPayment::class)(
        User::factory()->create(), BillingPlan::Monthly, PaymentNetwork::Arbitrum, SettlementAsset::Usdc
    );

    // The nonce pool is scoped per chain and asset, so a mainnet transfer can
    // never satisfy an Arbitrum intent even at the same price.
    expect($onArbitrum->expected_amount)->not->toBe($onMainnet->expected_amount);
});

test('a payment settles on Arbitrum through the same driver', function () {
    $amountHex = '0x5b8d80';

    $payment = SubscriptionPayment::factory()->submitted()->create([
        'network' => PaymentNetwork::Arbitrum,
        'chain_id' => 42161,
        'asset' => SettlementAsset::Usdc,
        'token_contract' => ARBITRUM_USDC,
        'asset_decimals' => 6,
        'pay_to_address' => mb_strtolower(TEST_RECEIVING_ADDRESS),
        'expected_amount' => TokenAmount::toDecimal(TokenAmount::fromHex($amountHex), 6),
        'months' => 1,
    ]);

    fakeEvmChain([
        'chain_id' => 42161,
        'confirmations' => 20,
        'tx_to' => ARBITRUM_USDC,
        'logs' => [evmTransferLog(ARBITRUM_USDC, TEST_RECEIVING_ADDRESS, $amountHex)],
    ]);

    (new VerifySubscriptionPaymentJob($payment->id))->handle(
        app(VerifyPaymentOnChain::class),
        app(GrantProAccess::class),
    );

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->user->fresh()->isPro())->toBeTrue();
});

test('a mainnet endpoint answering for Arbitrum is caught as misconfiguration', function () {
    $payment = SubscriptionPayment::factory()->submitted()->create([
        'network' => PaymentNetwork::Arbitrum,
        'chain_id' => 42161,
        'pay_to_address' => mb_strtolower(TEST_RECEIVING_ADDRESS),
    ]);

    // The RPC URL points at the wrong chain. Never the buyer's fault, so it must
    // not spend their money.
    fakeEvmChain(['chain_id' => 1]);

    (new VerifySubscriptionPaymentJob($payment->id))->handle(
        app(VerifyPaymentOnChain::class),
        app(GrantProAccess::class),
    );

    expect($payment->fresh()->status)->toBe(PaymentStatus::Submitted);
});

test('Etherscan is asked about the right chain, with the same key', function () {
    config()->set([
        'billing.etherscan.enabled' => true,
        'billing.etherscan.url' => 'https://etherscan.test/v2/api',
        'billing.etherscan.api_key' => 'test-key',
    ]);

    $payment = SubscriptionPayment::factory()->native()->submitted()->create([
        'network' => PaymentNetwork::Arbitrum,
        'chain_id' => 42161,
        'pay_to_address' => mb_strtolower(TEST_RECEIVING_ADDRESS),
        'expected_amount' => TokenAmount::toDecimal('1000000000000000000', 18),
    ]);

    fakeEvmChain([
        'chain_id' => 42161,
        'confirmations' => 20,
        'tx_to' => '0x7777777777777777777777777777777777777777',
        'value' => '0x0',
        'internal' => [[
            'to' => mb_strtolower(TEST_RECEIVING_ADDRESS),
            'value' => '1000000000000000000',
            'isError' => '0',
        ]],
    ]);

    (new VerifySubscriptionPaymentJob($payment->id))->handle(
        app(VerifyPaymentOnChain::class),
        app(GrantProAccess::class),
    );

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed);

    // One key, every chain — the chain travels as a query parameter, which is
    // what makes adding a network config-only on this path too.
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'etherscan.test')
        && str_contains($request->url(), 'chainid=42161'));
});

test('the billing page offers both chains and their own contracts', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('billing.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('networks', 2)
            ->where('networks.0.key', 'ethereum')
            ->where('networks.1.key', 'arbitrum')
            ->where('networks.1.chain_id', 42161)
            ->where('networks.1.assets.2.contract', ARBITRUM_USDC)
        );
});

test('a chain the config does not offer is refused at the door', function () {
    enableBothChains(['billing.networks.arbitrum.enabled' => false]);

    $this->actingAs(User::factory()->create())
        ->post(route('billing.payments.store'), [
            'plan' => 'monthly',
            'network' => 'arbitrum',
            'asset' => 'usdc',
        ])
        ->assertSessionHasErrors('network');
});
