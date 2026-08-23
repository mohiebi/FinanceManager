<?php

namespace App\Support\Billing;

use App\Enums\BillingPlan;
use App\Enums\DepositAddressStatus;
use App\Enums\PaymentNetwork;
use App\Enums\SettlementAsset;
use App\Models\CouponRedemption;
use App\Models\DepositAddress;
use App\Models\SubscriptionPayment;

/**
 * Shapes the billing page's props.
 *
 * Lives in Support rather than Services because it performs no IO — it only
 * reads config and models that have already been fetched.
 */
final readonly class BillingCatalog
{
    /** Whether anything can be sold at all right now. */
    public function isAvailable(): bool
    {
        return (bool) config('billing.enabled', false)
            && BillingPlan::available() !== []
            && PaymentNetwork::available() !== [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function plans(): array
    {
        $monthlyRate = $this->monthlyRate();

        return array_map(function (BillingPlan $plan) use ($monthlyRate): array {
            $perMonth = (float) $plan->priceUsd() / max(1, $plan->months());

            return [
                'key' => $plan->value,
                'label' => $plan->label(),
                'description' => $plan->description(),
                'months' => $plan->months(),
                'price_usd' => $plan->priceUsd(),
                'per_month_usd' => number_format($perMonth, 2, '.', ''),
                'highlighted' => $plan->isHighlighted(),
                // Null on the plan that sets the baseline, and on anything that
                // somehow costs more per month than it.
                'savings_percent' => $monthlyRate !== null && $perMonth < $monthlyRate
                    ? (int) round((1 - ($perMonth / $monthlyRate)) * 100)
                    : null,
            ];
        }, BillingPlan::available());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function networks(): array
    {
        return array_map(function (PaymentNetwork $network): array {
            $availableAddresses = DepositAddress::query()
                ->available()
                ->where(fn ($query) => $query
                    ->whereNull('network')
                    ->orWhere('network', $network->value))
                ->whereNotIn('address', DepositAddress::query()
                    ->where('status', '!=', DepositAddressStatus::Available->value)
                    ->select('address'))
                ->distinct()
                ->count('address');

            return [
                'key' => $network->value,
                'label' => $network->label(),
                'chain_id' => $network->chainId(),
                'available' => $availableAddresses > 0,
                'available_addresses' => $availableAddresses,
                'confirmations_required' => $network->confirmationsRequired(),
                'assets' => array_map(fn (SettlementAsset $asset): array => [
                    'key' => $asset->value,
                    'label' => $asset->label(),
                    'symbol' => $asset->symbol(),
                    'contract' => $asset->contractOn($network),
                    'decimals' => $asset->decimalsOn($network),
                    'display_precision' => $asset->displayPrecision(),
                    'is_stable' => $asset->isStable(),
                ], $network->assets()),
            ];
        }, PaymentNetwork::available());
    }

    /**
     * One row of the buyer's history for a payment.
     *
     * @return array{id: string, kind: string, plan_label: string|null, months: int, status_label: string, tone: string, price_usd: string, list_price_usd: string|null, coupon_code: string|null, explorer_url: string|null, failure_message: string|null, created_at: string, settled_at: string|null}
     */
    public function presentPaymentHistoryEntry(SubscriptionPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'kind' => 'payment',
            'plan_label' => $payment->plan->label(),
            'months' => $payment->months,
            'status_label' => $payment->status->label(),
            'tone' => $payment->status->tone(),
            'price_usd' => $payment->price_usd,
            'list_price_usd' => $payment->list_price_usd,
            'coupon_code' => $payment->coupon?->code,
            'explorer_url' => $payment->tx_hash !== null && $payment->network !== null
                ? $payment->network->explorerTxUrl($payment->tx_hash)
                : null,
            'failure_message' => $payment->failure_reason?->label(),
            'created_at' => $payment->created_at->toIso8601String(),
            'settled_at' => $payment->verified_at?->toIso8601String(),
        ];
    }

    /**
     * One row of the buyer's history for a coupon that covered the whole price.
     *
     * These have no payment behind them by design — a chain cannot carry a zero
     * transfer — which meant redeeming one put months on the account and left
     * the history showing nothing, or worse, showing only the intent the buyer
     * had abandoned to go and use the code. The entitlement moved, so the
     * history has to say so.
     *
     * `plan_label` is null because the grant records months, not a plan —
     * reconstructing "Monthly" from a month count would go wrong the first time
     * a plan's length is reconfigured. The page titles these rows from `months`
     * instead, using the same keys it already counts months with elsewhere;
     * doing it here would need Laravel's `:count`, and the billing translations
     * are read by vue-i18n, which interpolates `{count}` and would print the
     * colon form literally.
     *
     * @return array{id: string, kind: string, plan_label: string|null, months: int, status_label: string, tone: string, price_usd: string, list_price_usd: string|null, coupon_code: string|null, explorer_url: string|null, failure_message: string|null, created_at: string, settled_at: string|null}
     */
    public function presentCouponHistoryEntry(CouponRedemption $redemption): array
    {
        $grantedAt = ($redemption->grant?->created_at ?? $redemption->created_at)->toIso8601String();

        return [
            'id' => 'coupon-'.$redemption->getKey(),
            'kind' => 'coupon',
            'plan_label' => null,
            'months' => $redemption->grant?->months ?? 0,
            'status_label' => __('billing.history.coupon_status'),
            // Positive, because from the buyer's side this settled: they have the
            // months, and nothing is outstanding.
            'tone' => 'positive',
            'price_usd' => '0.00',
            // What the code was worth. It covered everything, so the discount is
            // the list price, and the row can strike it through like any other.
            'list_price_usd' => $redemption->discount_usd,
            'coupon_code' => $redemption->coupon?->code,
            // No transaction, so nothing to look up on a chain.
            'explorer_url' => null,
            'failure_message' => null,
            'created_at' => $grantedAt,
            'settled_at' => $grantedAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentPayment(SubscriptionPayment $payment): array
    {
        $riskCase = $payment->relationLoaded('riskCase') ? $payment->riskCase : null;

        return [
            'id' => $payment->id,
            'status' => $payment->status->value,
            'status_label' => $payment->status->label(),
            'tone' => $payment->status->tone(),
            'plan' => $payment->plan->value,
            'plan_label' => $payment->plan->label(),
            'months' => $payment->months,
            'price_usd' => $payment->price_usd,
            // Both null unless a coupon was applied, which is what the page uses
            // to decide whether to show a struck-through original price.
            'list_price_usd' => $payment->list_price_usd,
            'coupon_code' => $payment->coupon?->code,
            'network' => $payment->network?->value,
            'network_label' => $payment->network?->label(),
            'chain_id' => $payment->chain_id,
            'asset' => $payment->asset->value,
            'asset_symbol' => $payment->asset->symbol(),
            'asset_decimals' => $payment->asset_decimals,
            'token_contract' => $payment->token_contract,
            'pay_to_address' => $payment->pay_to_address,
            'expected_amount' => $payment->expected_amount,
            'received_amount' => $payment->received_amount,
            'quote_rate' => $payment->quote_rate,
            'quote_expires_at' => $payment->quote_expires_at?->toIso8601String(),
            'tx_hash' => $payment->tx_hash,
            'explorer_url' => $payment->tx_hash !== null && $payment->network !== null
                ? $payment->network->explorerTxUrl($payment->tx_hash)
                : null,
            'confirmations' => $payment->confirmations,
            'confirmations_required' => $payment->network?->confirmationsRequired(),
            'failure_reason' => $payment->failure_reason?->value,
            'failure_message' => $payment->failure_reason?->label(),
            'screening_risk' => $payment->screening_risk?->value,
            'review_deadline' => $riskCase?->review_expires_at?->toIso8601String(),
            'review_status' => $riskCase?->status->value,
            'payment_uri' => $this->paymentUri($payment),
            'created_at' => $payment->created_at->toIso8601String(),
            'expires_at' => $payment->expires_at->toIso8601String(),
            'verified_at' => $payment->verified_at?->toIso8601String(),
        ];
    }

    /**
     * An EIP-681 request a wallet can open with the amount already filled in.
     *
     * Worth the few lines: retyping an eighteen-decimal figure by hand is how a
     * payment ends up in the amount-mismatch queue.
     */
    private function paymentUri(SubscriptionPayment $payment): ?string
    {
        if ($payment->network === null || $payment->pay_to_address === null) {
            return null;
        }

        $chainId = $payment->chain_id;
        $baseUnits = $payment->expectedBaseUnits();

        if ($payment->token_contract === null) {
            return "ethereum:{$payment->pay_to_address}@{$chainId}?value={$baseUnits}";
        }

        return "ethereum:{$payment->token_contract}@{$chainId}/transfer"
            ."?address={$payment->pay_to_address}&uint256={$baseUnits}";
    }

    /**
     * The per-month price of the shortest plan, used as the savings baseline.
     */
    private function monthlyRate(): ?float
    {
        foreach (BillingPlan::available() as $plan) {
            if ($plan->months() === 1) {
                return (float) $plan->priceUsd();
            }
        }

        return null;
    }
}
