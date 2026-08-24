<?php

namespace App\Enums;

enum DepositAddressStatus: string
{
    case Available = 'available';
    case Assigned = 'assigned';
    case RiskReview = 'risk_review';
    case Settling = 'settling';
    case Retired = 'retired';
    case Swept = 'swept';
    case Recovered = 'recovered';
    case Quarantined = 'quarantined';
}
