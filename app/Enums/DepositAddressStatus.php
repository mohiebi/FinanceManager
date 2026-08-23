<?php

namespace App\Enums;

enum DepositAddressStatus: string
{
    case Available = 'available';
    case Assigned = 'assigned';
    case RiskReview = 'risk_review';
    case Settling = 'settling';
    case Retired = 'retired';
    case SweepAuthorized = 'sweep_authorized';
    case Swept = 'swept';
    case Quarantined = 'quarantined';
}
