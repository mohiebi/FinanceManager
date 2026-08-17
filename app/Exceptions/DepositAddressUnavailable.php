<?php

namespace App\Exceptions;

use App\Enums\PaymentNetwork;
use Exception;

class DepositAddressUnavailable extends Exception
{
    public function __construct(public readonly PaymentNetwork $network)
    {
        parent::__construct("No isolated deposit address is available for {$network->value}.");
    }
}
