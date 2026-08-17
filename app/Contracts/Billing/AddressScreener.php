<?php

namespace App\Contracts\Billing;

use App\Support\Billing\ScreeningResult;
use App\Support\Billing\ScreeningSubject;

interface AddressScreener
{
    public function screen(ScreeningSubject $subject): ScreeningResult;
}
