<?php

namespace App\Services\Billing;

use App\Contracts\Billing\AddressScreener;
use App\Support\Billing\ScreeningResult;
use App\Support\Billing\ScreeningSubject;

final readonly class NullAddressScreener implements AddressScreener
{
    public function screen(ScreeningSubject $subject): ScreeningResult
    {
        return ScreeningResult::unknown('null', 'screening_disabled');
    }
}
