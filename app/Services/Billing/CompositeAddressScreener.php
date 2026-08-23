<?php

namespace App\Services\Billing;

use App\Contracts\Billing\AddressScreener;
use App\Enums\ScreeningRisk;
use App\Support\Billing\ScreeningResult;
use App\Support\Billing\ScreeningSubject;

final readonly class CompositeAddressScreener implements AddressScreener
{
    /** @param array<int, AddressScreener> $screeners */
    public function __construct(private array $screeners) {}

    public function screen(ScreeningSubject $subject): ScreeningResult
    {
        foreach ($this->screeners as $screener) {
            $result = $screener->screen($subject);

            if ($result->risk !== ScreeningRisk::NoMatch) {
                return $result;
            }
        }

        return new ScreeningResult(ScreeningRisk::NoMatch, 'composite', screenedAt: now()->toImmutable());
    }
}
