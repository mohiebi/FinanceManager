<?php

namespace App\Enums;

enum BillRecurrenceType: string
{
    case OneTime = 'one_time';
    case Monthly = 'monthly';
}
