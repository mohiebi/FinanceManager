<?php

use App\Actions\Billing\AuthorizeRiskSettlement;
use App\Actions\Billing\FinalizeScreenedPayment;
use App\Actions\Billing\GrantRiskPayment;
use App\Enums\DepositAddressStatus;
use App\Enums\PaymentNetwork;
use App\Enums\PaymentStatus;
use App\Enums\RiskCaseStatus;
use App\Enums\ScreeningRisk;
use App\Enums\SettlementStatus;
use App\Jobs\ProcessPaymentSettlementJob;
use App\Jobs\RefillDepositAddressPoolJob;
use App\Models\DepositAddress;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\Billing\WalletSignerClient;
use App\Support\Billing\ScreeningResult;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    enableBilling();
    Queue::fake();
});

function verifiedPayment(string $sender, int $sequence = 1, ?User $user = null): SubscriptionPayment
{
    $address = '0x'.str_pad(dechex(10_000 + $sequence), 40, '0', STR_PAD_LEFT);
    $payment = SubscriptionPayment::factory()->submitted('0x'.str_pad(dechex($sequence), 64, '0', STR_PAD_LEFT))->create([
        'user_id' => ($user ?? User::factory()->create())->getKey(),
        'pay_to_address' => $address,
        'from_address' => mb_strtolower($sender),
        'received_amount' => '5.004317',
        'chain_verified_at' => now(),
    ]);
    DepositAddress::factory()->create([
        'network' => PaymentNetwork::Ethereum,
        'derivation_index' => 10_000 + $sequence,
        'address' => $address,
        'status' => DepositAddressStatus::Assigned,
        'assigned_payment_id' => $payment->getKey(),
        'assigned_at' => now(),
    ]);

    return $payment->fresh(['user', 'depositAddress']);
}

test('clean screening grants Pro immediately and queues settlement independently', function () {
    $payment = verifiedPayment('0x2222222222222222222222222222222222222222');

    app(FinalizeScreenedPayment::class)($payment, new ScreeningResult(
        ScreeningRisk::NoMatch,
        'test',
        screenedAt: now()->toImmutable(),
    ));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->user->fresh()->isPro())->toBeTrue()
        ->and($payment->settlement()->sole()->status)->toBe(SettlementStatus::Queued);
    Queue::assertPushed(ProcessPaymentSettlementJob::class);
});

test('a flagged address receives only one global exception and a user receives at most three', function () {
    $sender = '0x3333333333333333333333333333333333333333';
    $first = verifiedPayment($sender, 1);
    app(FinalizeScreenedPayment::class)($first, new ScreeningResult(ScreeningRisk::Flagged, 'local', screenedAt: now()->toImmutable()));

    expect($first->fresh()->status)->toBe(PaymentStatus::RiskReview)
        ->and(now()->diffInHours($first->riskCase()->sole()->review_expires_at))->toBeGreaterThanOrEqual(47);

    $reused = verifiedPayment($sender, 2);
    app(FinalizeScreenedPayment::class)($reused, new ScreeningResult(ScreeningRisk::Flagged, 'local', screenedAt: now()->toImmutable()));
    expect($reused->fresh()->status)->toBe(PaymentStatus::Failed);

    $user = User::factory()->create();
    foreach (range(3, 6) as $sequence) {
        $payment = verifiedPayment('0x'.str_pad(dechex(100 + $sequence), 40, '0', STR_PAD_LEFT), $sequence, $user);
        app(FinalizeScreenedPayment::class)($payment, new ScreeningResult(ScreeningRisk::Flagged, 'local', screenedAt: now()->toImmutable()));
    }

    expect($user->paymentRiskCases()->count())->toBe(3)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Failed);
});

test('flagged Pro can be granted only after authorized settlement succeeds', function () {
    $payment = verifiedPayment('0x4444444444444444444444444444444444444444', 20);
    app(FinalizeScreenedPayment::class)($payment, new ScreeningResult(ScreeningRisk::Flagged, 'local', screenedAt: now()->toImmutable()));
    $case = $payment->riskCase()->sole();
    $admin = User::factory()->create();

    app(AuthorizeRiskSettlement::class)($case, $admin, 'Reviewed source and authorizing the fixed settlement.');
    expect($case->fresh()->status)->toBe(RiskCaseStatus::Authorized)
        ->and($payment->settlement()->sole()->risk_authorized)->toBeTrue();

    $payment->settlement()->sole()->forceFill(['status' => SettlementStatus::Completed])->save();
    $case->forceFill(['status' => RiskCaseStatus::Settled])->save();
    app(GrantRiskPayment::class)($case, $admin, 'Settlement reached the Safe Vault.');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->user->fresh()->isPro())->toBeTrue()
        ->and($case->fresh()->status)->toBe(RiskCaseStatus::Granted);
});

test('pool refill accepts only the expected deterministic signer batch', function () {
    DepositAddress::query()->delete();
    config()->set(['billing.deposit_pool.target' => 2, 'billing.signer.secret_file' => $path = tempnam(sys_get_temp_dir(), 'signer-secret-')]);
    file_put_contents($path, str_repeat('a', 64));
    Http::fake(function (Request $request) {
        expect($request->hasHeader('X-Signer-Signature'))->toBeTrue();

        return Http::response([
            'key_version' => 'v1',
            'start_index' => 0,
            'addresses' => [
                ['index' => 0, 'address' => '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
                ['index' => 1, 'address' => '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],
            ],
        ]);
    });

    app(RefillDepositAddressPoolJob::class)->handle(app(WalletSignerClient::class));

    expect(DepositAddress::query()->available()->count())->toBe(2)
        ->and(DepositAddress::query()->whereNotNull('network')->count())->toBe(0);
    unlink($path);
});
