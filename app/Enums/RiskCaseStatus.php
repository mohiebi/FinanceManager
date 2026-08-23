<?php

namespace App\Enums;

enum RiskCaseStatus: string
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case SettlementFailed = 'settlement_failed';
    case Settled = 'settled';
    case Granted = 'granted';
    case Denied = 'denied';
    case Expired = 'expired';
}
