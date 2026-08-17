<?php

use App\Actions\Billing\AuthorizeDepositSweep;
use App\Actions\Billing\GrantProAccess;
use App\Actions\Billing\RecordDepositSweep;
use App\Contracts\Billing\AddressScreener;
use App\Enums\DepositAddressStatus;
use App\Enums\GrantReason;
use App\Enums\PaymentStatus;
use App\Enums\ScreeningRisk;
use App\Enums\ScreeningStage;
use App\Exceptions\SweepAuthorizationDenied;
use App\Exceptions\SweepRecordRejected;
use App\Models\DepositAddress;
use App\Models\PaymentScreening;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\Billing\ScreeningResult;
use App\Support\Billing\ScreeningSubject;
use Illuminate\Support\Facades\Http;

const SWEEP_HASH = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const CONVERSION_HASH = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
const TREASURY_ADDRESS = '0x3333333333333333333333333333333333333333';

beforeEach(function () {
    enableBilling([
        'billing.sweep.cooling_hours' => 72,
        'billing.sweep.authorization_minutes' => 30,
        'billing.sweep.treasury.ethereum' => TREASURY_ADDRESS,
    ]);
    Http::preventStrayRequests();
});

function sweepScreener(ScreeningRisk $risk): AddressScreener
{
    return new class($risk) implements AddressScreener
    {
        public function __construct(private readonly ScreeningRisk $risk) {}

        public function screen(ScreeningSubject $subject): ScreeningResult
        {
            return new ScreeningResult($this->risk, 'sweep_test', screenedAt: now()->toImmutable());
        }
    };
}

function sweepableDeposit(bool $cooled = true, bool $stablecoin = false): DepositAddress
{
    static $sequence = 0;

    $deposit = DepositAddress::query()->available()->where('network', 'ethereum')->firstOrFail();
    $factory = SubscriptionPayment::factory();

    if (! $stablecoin) {
        $factory = $factory->native();
    }

    $payment = $factory->confirmed()->create([
        'pay_to_address' => $deposit->address,
        'tx_hash' => '0x'.str_pad(dechex(++$sequence), 64, 'b', STR_PAD_LEFT),
        'status' => PaymentStatus::Confirmed,
        'screening_risk' => ScreeningRisk::NoMatch,
        'screening_provider' => 'test_provider',
        'screened_at' => now()->subHours(73),
        'chain_verified_at' => now()->subHours(73),
        'block_timestamp' => $cooled ? now()->subHours(73) : now()->subHours(71),
    ]);

    $deposit->forceFill([
        'status' => DepositAddressStatus::Assigned,
        'assigned_payment_id' => $payment->id,
        'assigned_at' => now()->subHours(73),
    ])->save();

    PaymentScreening::factory()->create([
        'subscription_payment_id' => $payment->id,
        'stage' => ScreeningStage::Settlement,
        'risk' => ScreeningRisk::NoMatch,
    ]);

    return $deposit->fresh('payment');
}

function fakeSweepRpc(DepositAddress $deposit, array $overrides = []): void
{
    Http::fake(function ($request) use ($deposit, $overrides) {
        $entries = [];

        foreach ($request->data() as $call) {
            $result = match ($call['method']) {
                'eth_chainId' => '0x'.dechex($overrides['chain_id'] ?? 1),
                'eth_blockNumber' => '0x100',
                'eth_getTransactionByHash' => [
                    'from' => $overrides['sender'] ?? $deposit->address,
                    'to' => ($call['params'][0] ?? '') === SWEEP_HASH
                        ? ($overrides['recipient'] ?? TREASURY_ADDRESS)
                        : '0x4444444444444444444444444444444444444444',
                ],
                'eth_getTransactionReceipt' => [
                    'status' => $overrides['status'] ?? '0x1',
                    'blockNumber' => $overrides['block_number'] ?? '0xf0',
                ],
                'eth_getBalance' => $overrides['native_balance'] ?? '0x0',
                'eth_call' => $overrides['token_balance'] ?? '0x0',
                default => null,
            };

            $entries[] = ['jsonrpc' => '2.0', 'id' => $call['id'], 'result' => $result];
        }

        return Http::response($entries);
    });
}

test('cooling and unknown fresh screening block sweep authorization', function () {
    $admin = User::factory()->create();
    $cooling = sweepableDeposit(cooled: false);

    expect(fn () => app(AuthorizeDepositSweep::class)($cooling, $admin))
        ->toThrow(SweepAuthorizationDenied::class, 'cooling');

    $ready = sweepableDeposit();
    $action = new AuthorizeDepositSweep(sweepScreener(ScreeningRisk::Unknown));

    expect(fn () => $action($ready, $admin))
        ->toThrow(SweepAuthorizationDenied::class, 'screening_unknown');

    expect($ready->fresh()->status)->toBe(DepositAddressStatus::Assigned)
        ->and($ready->payment->fresh()->status)->toBe(PaymentStatus::Confirmed);

    (new AuthorizeDepositSweep(sweepScreener(ScreeningRisk::NoMatch)))($ready->fresh('payment'), $admin);

    expect($ready->fresh()->status)->toBe(DepositAddressStatus::SweepAuthorized);
});

test('a second sanctioned screening quarantines funds without revoking existing pro access', function () {
    $admin = User::factory()->create();
    $deposit = sweepableDeposit();
    $payment = $deposit->payment;
    app(GrantProAccess::class)($payment->user, 3, GrantReason::Payment, $payment->id);
    $proUntil = $payment->user->fresh()->pro_until;
    $action = new AuthorizeDepositSweep(sweepScreener(ScreeningRisk::Sanctioned));

    expect(fn () => $action($deposit, $admin))
        ->toThrow(SweepAuthorizationDenied::class, 'quarantined');

    expect($deposit->fresh()->status)->toBe(DepositAddressStatus::Quarantined)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->fresh()->failure_reason)->toBeNull()
        ->and($payment->user->fresh()->pro_until->equalTo($proUntil))->toBeTrue();
});

test('authorization expires after thirty minutes', function () {
    $admin = User::factory()->create();
    $deposit = sweepableDeposit();
    (new AuthorizeDepositSweep(sweepScreener(ScreeningRisk::NoMatch)))($deposit, $admin);

    expect($deposit->fresh()->status)->toBe(DepositAddressStatus::SweepAuthorized)
        ->and(now()->diffInMinutes($deposit->fresh()->sweep_authorization_expires_at))->toBeGreaterThanOrEqual(29);

    $deposit->forceFill(['sweep_authorization_expires_at' => now()->subSecond()])->save();

    expect(fn () => app(RecordDepositSweep::class)(
        $deposit->fresh('payment'),
        $admin,
        'Hardware wallet sweep',
        SWEEP_HASH,
    ))->toThrow(SweepRecordRejected::class, 'authorization_expired');
});

test('a confirmed direct eth sweep to treasury with no balances is recorded', function () {
    $admin = User::factory()->create();
    $deposit = sweepableDeposit();
    (new AuthorizeDepositSweep(sweepScreener(ScreeningRisk::NoMatch)))($deposit, $admin);
    $deposit = $deposit->fresh('payment');
    fakeSweepRpc($deposit);

    app(RecordDepositSweep::class)(
        $deposit,
        $admin,
        'Signed on the deposit hardware wallet.',
        SWEEP_HASH,
    );

    expect($deposit->fresh()->status)->toBe(DepositAddressStatus::Swept)
        ->and($deposit->fresh()->sweep_tx_hash)->toBe(SWEEP_HASH)
        ->and($deposit->fresh()->conversion_tx_hash)->toBeNull()
        ->and($deposit->fresh()->swept_by_admin_id)->toBe($admin->id);
});

test('stablecoins require a confirmed conversion before the eth sweep', function () {
    $admin = User::factory()->create();
    $deposit = sweepableDeposit(stablecoin: true);
    (new AuthorizeDepositSweep(sweepScreener(ScreeningRisk::NoMatch)))($deposit, $admin);

    expect(fn () => app(RecordDepositSweep::class)(
        $deposit->fresh('payment'),
        $admin,
        'Missing conversion',
        SWEEP_HASH,
    ))->toThrow(SweepRecordRejected::class, 'conversion_required');

    $deposit = $deposit->fresh('payment');
    fakeSweepRpc($deposit);
    app(RecordDepositSweep::class)(
        $deposit,
        $admin,
        'Converted with hardware wallet and swept.',
        SWEEP_HASH,
        CONVERSION_HASH,
    );

    expect($deposit->fresh()->status)->toBe(DepositAddressStatus::Swept)
        ->and($deposit->fresh()->conversion_tx_hash)->toBe(CONVERSION_HASH);
});

test('wrong chain recipient confirmation token balance and gas dust all fail closed', function (array $rpc, string $reason) {
    $admin = User::factory()->create();
    $deposit = sweepableDeposit();
    (new AuthorizeDepositSweep(sweepScreener(ScreeningRisk::NoMatch)))($deposit, $admin);
    $deposit = $deposit->fresh('payment');
    fakeSweepRpc($deposit, $rpc);

    expect(fn () => app(RecordDepositSweep::class)(
        $deposit,
        $admin,
        'Validation must fail.',
        SWEEP_HASH,
    ))->toThrow(SweepRecordRejected::class, $reason);
})->with([
    'wrong chain' => [['chain_id' => 42161], 'wrong_chain'],
    'wrong treasury' => [['recipient' => '0x5555555555555555555555555555555555555555'], 'sweep_wrong_recipient'],
    'not confirmed' => [['block_number' => '0x100'], 'sweep_unconfirmed'],
    'token remains' => [['token_balance' => '0x1'], 'token_balance_remaining'],
    'too much gas dust' => [['native_balance' => '0x2386f26fc10001'], 'native_dust_too_high'],
]);
