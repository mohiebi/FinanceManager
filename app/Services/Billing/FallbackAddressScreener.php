<?php

namespace App\Services\Billing;

use App\Contracts\Billing\AddressScreener;
use App\Support\Billing\ScreeningResult;
use App\Support\Billing\ScreeningSubject;

final readonly class FallbackAddressScreener implements AddressScreener
{
    /** @param  array<int, AddressScreener>  $screeners */
    public function __construct(private array $screeners) {}

    public function screen(ScreeningSubject $subject): ScreeningResult
    {
        $last = null;

        foreach ($this->screeners as $screener) {
            $result = $screener->screen($subject);

            if ($result->risk->isDefinitive()) {
                return $result;
            }

            $last = $result;
        }

        return $last ?? ScreeningResult::unknown('fallback', 'no_screening_driver');
    }
}
