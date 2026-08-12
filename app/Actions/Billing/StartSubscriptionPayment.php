<?php

namespace App\Actions\Billing;

use App\Enums\BillingPlan;
use App\Enums\PaymentNetwork;
use App\Enums\PaymentStatus;
use App\Enums\SettlementAsset;
use App\Exceptions\QuoteUnavailable;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\Billing\AssetQuoteService;
use App\Support\Billing\TokenAmount;
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

    public function __construct(private AssetQuoteService $quotes) {}

    /**
     * @throws QuoteUnavailable when no trustworthy rate is available
     * @throws RuntimeException when the requested rail cannot take a payment
     */
    public function __invoke(
        User $user,
        BillingPlan $plan,
        PaymentNetwork $network,
        SettlementAsset $asset,
    ): SubscriptionPayment {
        $this->assertPayable($plan, $network, $asset);

        // Tapping a plan card five times must not leave five intents behind, so
        // an open one on identical terms is handed back unchanged — including
        // its amount, which the buyer may already have copied into a wallet.
        $existing = $this->openIntentFor($user, $plan, $network, $asset);

        if ($existing !== null) {
            return $existing;
        }

        $usdRate = $this->quotes->usdRate($asset);
        $quantized = $this->quotes->priceIn($asset, $plan->priceUsd(), $usdRate);
        $decimals = $asset->decimalsOn($network);

        return SubscriptionPayment::create([
            'user_id' => $user->getKey(),
            'status' => PaymentStatus::Pending,
            'plan' => $plan,
            'months' => $plan->months(),
            'price_usd' => $plan->priceUsd(),
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

    private function assertPayable(BillingPlan $plan, PaymentNetwork $network, SettlementAsset $asset): void
    {
        // The controller has already refused all of this, and the form request
        // before it. Repeating it here is the same posture UpdateUserFeature
        // takes: an action that hands out entitlement must not depend on its
        // callers having been careful.
        if (! config('billing.enabled', false)) {
            throw new RuntimeException('Billing is switched off.');
        }

        if (! in_array($plan, BillingPlan::available(), true)) {
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
        BillingPlan $plan,
        PaymentNetwork $network,
        SettlementAsset $asset,
    ): ?SubscriptionPayment {
        return SubscriptionPayment::query()
            ->open()
            ->where('user_id', $user->getKey())
            ->where('plan', $plan->value)
            ->where('network', $network->value)
            ->where('asset', $asset->value)
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
