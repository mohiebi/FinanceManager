<?php

namespace App\Enums;

enum AdvisorRecommendationMode: string
{
    case Rebalance = 'rebalance';
    case TargetOnly = 'target_only';
}
