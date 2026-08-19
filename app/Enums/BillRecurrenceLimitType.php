<?php

namespace App\Enums;

enum BillRecurrenceLimitType: string
{
    case Infinite = 'infinite';
    case Count = 'count';
    case Date = 'date';
}
