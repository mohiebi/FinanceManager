<?php

namespace App\Exceptions;

use Exception;

class DepositAddressLimitExceeded extends Exception
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct("The daily limit of {$limit} new payment addresses has been reached.");
    }
}
