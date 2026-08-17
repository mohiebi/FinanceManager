<?php

namespace App\Enums;

enum DepositAddressStatus: string
{
    case Available = 'available';
    case Assigned = 'assigned';
    case Retired = 'retired';
    case SweepAuthorized = 'sweep_authorized';
    case Swept = 'swept';
    case Quarantined = 'quarantined';
}
