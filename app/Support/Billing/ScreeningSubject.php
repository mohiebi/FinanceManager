<?php

namespace App\Support\Billing;

use App\Enums\PaymentNetwork;
use App\Enums\SettlementAsset;
use App\Models\SubscriptionPayment;

final readonly class ScreeningSubject
{
    public function __construct(
        public PaymentNetwork $network,
        public string $transactionHash,
        public string $senderAddress,
        public string $recipientAddress,
        public SettlementAsset $asset,
        public string $receivedAmount,
    ) {}

    public static function fromPayment(SubscriptionPayment $payment): self
    {
        return new self(
            network: $payment->network,
            transactionHash: (string) $payment->tx_hash,
            senderAddress: (string) $payment->from_address,
            recipientAddress: (string) $payment->pay_to_address,
            asset: $payment->asset,
            receivedAmount: (string) $payment->received_amount,
        );
    }
}
