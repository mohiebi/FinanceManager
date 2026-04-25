<?php

namespace App\Enums;

enum TransactionType: string
{
    case Cost = 'cost';
    case Income = 'income';
}
