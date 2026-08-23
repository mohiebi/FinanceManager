<?php

namespace App\Services\Billing;

use App\Contracts\Billing\AddressScreener;
use App\Enums\ScreeningRisk;
use App\Support\Billing\ScreeningResult;
use App\Support\Billing\ScreeningSubject;

final readonly class SenderAndDepositSanctionsScreener implements AddressScreener
{
    public function __construct(private AddressScreener $screener) {}

    public function screen(ScreeningSubject $subject): ScreeningResult
    {
        $sender = $this->screener->screen($subject);
        $deposit = $this->screener->screen(new ScreeningSubject(
            network: $subject->network,
            transactionHash: $subject->transactionHash,
            senderAddress: $subject->recipientAddress,
            recipientAddress: $subject->recipientAddress,
            asset: $subject->asset,
            receivedAmount: $subject->receivedAmount,
        ));

        if ($sender->risk === ScreeningRisk::Sanctioned) {
            return $sender;
        }

        if ($deposit->risk === ScreeningRisk::Sanctioned) {
            return $deposit;
        }

        if ($sender->risk === ScreeningRisk::Unknown) {
            return $sender;
        }

        return $deposit;
    }
}
