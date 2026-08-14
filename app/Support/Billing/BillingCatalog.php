<?php

namespace App\Support\Billing;

use App\Enums\BillingPlan;
use App\Enums\PaymentNetwork;
use App\Enums\SettlementAsset;
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
        return array_map(fn (PaymentNetwork $network): array => [
            'key' => $network->value,
            'label' => $network->label(),
            'chain_id' => $network->chainId(),
            'address' => $network->receivingAddress(),
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
        ], PaymentNetwork::available());
    }

    /**
     * @return array<string, mixed>
     */
    public function presentPayment(SubscriptionPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'status' => $payment->status->value,
            'status_label' => $payment->status->label(),
            'tone' => $payment->status->tone(),
            'plan' => $payment->plan->value,
            'plan_label' => $payment->plan->label(),
            'months' => $payment->months,
            'price_usd' => $payment->price_usd,
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
