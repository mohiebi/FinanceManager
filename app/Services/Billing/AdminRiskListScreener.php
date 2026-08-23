<?php

namespace App\Services\Billing;

use App\Contracts\Billing\AddressScreener;
use App\Enums\ScreeningRisk;
use App\Models\RiskAddress;
use App\Support\Billing\ScreeningResult;
use App\Support\Billing\ScreeningSubject;

final readonly class AdminRiskListScreener implements AddressScreener
{
    public function screen(ScreeningSubject $subject): ScreeningResult
    {
        $addresses = [
            $subject->network->normalizeAddress($subject->senderAddress),
            $subject->network->normalizeAddress($subject->recipientAddress),
        ];

        $entry = RiskAddress::query()
            ->where('network', $subject->network->value)
            ->whereIn('address', $addresses)
            ->where('active', true)
            ->first();

        if ($entry === null) {
            return new ScreeningResult(ScreeningRisk::NoMatch, 'admin_risk_list', screenedAt: now()->toImmutable());
        }

        return new ScreeningResult(
            risk: ScreeningRisk::Flagged,
            provider: 'admin_risk_list',
            categories: ['local_risk'],
            providerReference: (string) $entry->getKey(),
            screenedAt: now()->toImmutable(),
        );
    }
}
