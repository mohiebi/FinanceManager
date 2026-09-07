<?php

namespace App\Actions\Billing;

use App\Enums\BillingPlan;
use App\Enums\CouponRedemptionStatus;
use App\Enums\CouponRejection;
use App\Enums\MilesPack;
use App\Enums\PaymentNetwork;
use App\Enums\PaymentStatus;
use App\Enums\SettlementAsset;
use App\Exceptions\CouponUnavailable;
use App\Exceptions\QuoteUnavailable;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\Billing\AssetQuoteService;
use App\Support\Billing\CouponDiscount;
use App\Support\Billing\TokenAmount;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Opens a payment intent: fixes the terms, and tells the buyer exactly what to
 * send where.
 *
 * The amount it produces is deliberately unique among every intent currently
 * open on the same chain and asset. That is what stops one buyer's payment from
 * ever satisfying another buyer's intent — the defence against somebody
 * watching the receiving address and claiming a stranger's transaction as their
 * own.
 */
final readonly class StartSubscriptionPayment
{
    /**
     * How many distinct amounts the nonce can produce for one price.
     */
    private const NONCE_MAX = 9999;

    /**
     * Attempts to find an amount no other open intent is already expecting.
     *
     * Only exhausted if thousands of intents are open for one plan at one price
     * at one moment, which is a nice problem and still not a silent one.
     */
    private const NONCE_ATTEMPTS = 50;

    public function __construct(
        private AssetQuoteService $quotes,
        private ResolveCoupon $resolveCoupon,
    ) {}

    /**
     * @throws QuoteUnavailable when no trustworthy rate is available
     * @throws RuntimeException when the requested rail cannot take a payment
     * @throws CouponUnavailable when a supplied coupon can no longer be claimed
     */
    public function __invoke(
        User $user,
        BillingPlan|MilesPack $plan,
        PaymentNetwork $network,
        SettlementAsset $asset,
        ?Coupon $coupon = null,
    ): SubscriptionPayment {
        $this->assertPayable($plan, $network, $asset);

        // Tapping a plan card five times must not leave five intents behind, so
        // an open one on identical terms is handed back unchanged — including
        // its amount, which the buyer may already have copied into a wallet.
        //
        // The coupon is part of "identical terms": without it, applying a code
        // to a plan already sitting in an open full-price intent would hand that
        // intent straight back and silently ignore the discount.
        $existing = $this->openIntentFor($user, $plan, $network, $asset, $coupon);

        if ($existing !== null) {
            return $existing;
        }

        $listPrice = $plan->priceUsd();

        if ($coupon === null) {
            return $this->create($user, $plan, $network, $asset, $listPrice, null, null, null);
        }

        // Everything from the limit re-check to the redemption row happens under
        // one lock on the coupon, so two buyers cannot both claim its last use.
        return DB::transaction(function () use ($user, $plan, $network, $asset, $listPrice, $coupon): SubscriptionPayment {
            $locked = Coupon::query()->whereKey($coupon->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                throw new CouponUnavailable(CouponRejection::NotFound);
            }

            $rejection = $this->resolveCoupon->rejectionFor($user, $locked);

            if ($rejection !== null) {
                throw new CouponUnavailable($rejection);
            }

            $discount = CouponDiscount::amountOff($listPrice, $locked);
            $payable = CouponDiscount::finalPrice($listPrice, $locked);

            // A coupon covering the whole price has no intent to open — the
            // caller is expected to have sent it to RedeemFreeCoupon instead.
            if (CouponDiscount::coversEverything($listPrice, $locked)) {
                throw new RuntimeException("Coupon {$locked->code} leaves nothing to pay; redeem it rather than opening an intent.");
            }

            $payment = $this->create($user, $plan, $network, $asset, $payable, $locked, $listPrice, $discount);

            CouponRedemption::create([
                'coupon_id' => $locked->getKey(),
                'user_id' => $user->getKey(),
                'subscription_payment_id' => $payment->getKey(),
                // Reserved, not consumed: the buyer has claimed a use but has not
                // paid for it yet. Released again if the intent lapses.
                'status' => CouponRedemptionStatus::Reserved,
                'discount_usd' => $discount,
            ]);

            return $payment;
        });
    }

    /**
     * Write the intent itself.
     *
     * `$payableUsd` is what the buyer actually owes, discounted or not, and it
     * is deliberately what both the quote and `price_usd` are built from: the
     * expected on-chain amount is derived from it, so storing anything else
     * there would leave the snapshot and the chain disagreeing and every
     * discounted payment failing on an amount mismatch.
     */
    private function create(
        User $user,
        BillingPlan|MilesPack $plan,
        PaymentNetwork $network,
        SettlementAsset $asset,
        string $payableUsd,
        ?Coupon $coupon,
        ?string $listPriceUsd,
        ?string $discountUsd,
    ): SubscriptionPayment {
        $usdRate = $this->quotes->usdRate($asset);
        $quantized = $this->quotes->priceIn($asset, $payableUsd, $usdRate);
        $decimals = $asset->decimalsOn($network);

        return SubscriptionPayment::create([
            'user_id' => $user->getKey(),
            'status' => PaymentStatus::Pending,
            'plan' => $plan instanceof BillingPlan ? $plan : null,
            'miles_pack' => $plan instanceof MilesPack ? $plan : null,
            'miles' => $plan instanceof MilesPack ? $plan->miles() : null,
            'months' => $plan instanceof BillingPlan ? $plan->months() : 0,
            'price_usd' => $payableUsd,
            'coupon_id' => $coupon?->getKey(),
            'list_price_usd' => $listPriceUsd,
            'network' => $network,
            'chain_id' => $network->chainId(),
            'asset' => $asset,
            'token_contract' => $asset->contractOn($network),
            'asset_decimals' => $decimals,
            'pay_to_address' => $network->receivingAddress(),
            'quote_rate' => $usdRate,
            'quote_expires_at' => now()->addMinutes($asset->quoteLockMinutes()),
            'expected_amount' => $this->distinctAmount($network, $asset, $quantized, $decimals),
            'expires_at' => now()->addHours((int) config('billing.payment_window_hours', 24)),
        ]);
    }

    private function assertPayable(BillingPlan|MilesPack $plan, PaymentNetwork $network, SettlementAsset $asset): void
    {
        // The controller has already refused all of this, and the form request
        // before it. Repeating it here is the same posture UpdateUserFeature
        // takes: an action that hands out entitlement must not depend on its
        // callers having been careful.
        if (! config('billing.enabled', false)) {
            throw new RuntimeException('Billing is switched off.');
        }

        if (! in_array($plan, ($plan instanceof MilesPack ? MilesPack::available() : BillingPlan::available()), true)) {
            throw new RuntimeException("The {$plan->value} plan is not for sale.");
        }

        if (! $network->isEnabled()) {
            throw new RuntimeException("The {$network->value} network cannot take payments.");
        }

        if (! $asset->isAvailableOn($network)) {
            throw new RuntimeException("{$asset->symbol()} cannot be paid on {$network->label()}.");
        }

        if ($asset->decimalsOn($network) < $asset->noncePrecision()) {
            throw new RuntimeException(
                "{$asset->symbol()} on {$network->label()} has too few decimals to carry a payment nonce."
            );
        }
    }

    private function openIntentFor(
        User $user,
        BillingPlan|MilesPack $plan,
        PaymentNetwork $network,
        SettlementAsset $asset,
        ?Coupon $coupon,
    ): ?SubscriptionPayment {
        return SubscriptionPayment::query()
            ->open()
            ->where('user_id', $user->getKey())
            ->where($plan instanceof MilesPack ? 'miles_pack' : 'plan', $plan->value)
            ->where('network', $network->value)
            ->where('asset', $asset->value)
            // The coupon is part of what makes two intents the same. Matching
            // without it would hand a full-price intent back to somebody who
            // just applied a code, or a discounted one to somebody who did not.
            ->when($coupon === null,
                fn ($query) => $query->whereNull('coupon_id'),
                fn ($query) => $query->where('coupon_id', $coupon->getKey()),
            )
            ->where('quote_expires_at', '>', now())
            ->latest('created_at')
            ->first();
    }

    /**
     * Add a nonce to the quoted price that no other open intent is using.
     *
     * The nonce occupies decimal places strictly below the quoted precision, so
     * it changes the amount by a fraction of a cent while making it unique. The
     * match at verification is then exact — no tolerance band, because a band
     * wide enough to be useful would be wide enough to span a neighbouring
     * intent's amount and undo the whole point of the nonce.
     */
    private function distinctAmount(
        PaymentNetwork $network,
        SettlementAsset $asset,
        string $quantized,
        int $decimals,
    ): string {
        $base = TokenAmount::fromDecimal($quantized, $decimals);

        // Trailing zeros that lift the nonce out of the asset's own precision
        // and into the digits the quoted price left empty.
        $scale = str_repeat('0', $decimals - $asset->noncePrecision());

        $taken = SubscriptionPayment::query()
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Submitted->value])
            ->where('network', $network->value)
            ->where('asset', $asset->value)
            ->pluck('expected_amount')
            ->map(fn (string $amount): string => TokenAmount::fromDecimal($amount, $decimals))
            ->all();

        for ($attempt = 0; $attempt < self::NONCE_ATTEMPTS; $attempt++) {
            $candidate = TokenAmount::add($base, random_int(1, self::NONCE_MAX).$scale);

            if (! in_array($candidate, $taken, true)) {
                return TokenAmount::toDecimal($candidate, $decimals);
            }
        }

        throw new RuntimeException(
            "Could not find a free payment amount for {$asset->symbol()} on {$network->label()}."
        );
    }
}
