<?php

namespace Database\Factories;

use App\Enums\BillingPlan;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentNetwork;
use App\Enums\PaymentStatus;
use App\Enums\SettlementAsset;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPayment>
 */
class SubscriptionPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Defaults to a pending USDT-on-Ethereum intent, the cheapest rail to reason
     * about: a stablecoin needs no rate lookup, so a test using it never has to
     * fake a quote.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => PaymentStatus::Pending,
            'plan' => BillingPlan::Monthly,
            'months' => 1,
            'price_usd' => '5.00',
            'network' => PaymentNetwork::Ethereum,
            'chain_id' => 1,
            'asset' => SettlementAsset::Usdt,
            'token_contract' => '0xdac17f958d2ee523a2206206994597c13d831ec7',
            'asset_decimals' => 6,
            'pay_to_address' => '0x1111111111111111111111111111111111111111',
            'quote_rate' => '1.00000000',
            'quote_expires_at' => now()->addDay(),
            'expected_amount' => '5.004317',
            'expires_at' => now()->addDay(),
        ];
    }

    /**
     * Settle in the chain's own currency instead of a token.
     */
    public function native(): static
    {
        return $this->state(fn (array $attributes): array => [
            'asset' => SettlementAsset::Eth,
            'token_contract' => null,
            'asset_decimals' => 18,
            'quote_rate' => '2000.00000000',
            'expected_amount' => '0.002500001234',
        ]);
    }

    /**
     * A transaction has been claimed and is waiting on the chain.
     */
    public function submitted(?string $txHash = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Submitted,
            'tx_hash' => $txHash ?? '0x'.str_repeat('a', 64),
            'submitted_at' => now(),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Confirmed,
            'tx_hash' => '0x'.str_repeat('b', 64),
            'from_address' => '0x2222222222222222222222222222222222222222',
            'received_amount' => $attributes['expected_amount'] ?? '5.004317',
            'confirmations' => 12,
            'block_number' => 21_000_000,
            'block_timestamp' => now()->subMinutes(5),
            'submitted_at' => now()->subMinutes(6),
            'verified_at' => now(),
        ]);
    }

    public function failed(PaymentFailureReason $reason = PaymentFailureReason::AmountMismatch): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Failed,
            'tx_hash' => '0x'.str_repeat('c', 64),
            'failure_reason' => $reason,
            'submitted_at' => now()->subMinutes(10),
        ]);
    }

    /**
     * An intent whose window closed unpaid.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Expired,
            'expires_at' => now()->subHour(),
            'failure_reason' => PaymentFailureReason::Expired,
        ]);
    }

    /**
     * Still pending, but past its window — what the sweep looks for.
     */
    public function stale(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Pending,
            'expires_at' => now()->subHour(),
        ]);
    }
}
