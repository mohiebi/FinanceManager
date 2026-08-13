<?php

namespace App\Enums;

enum AdvisorRecommendationStatus: string
{
    case Generating = 'generating';
    case NeedsClarification = 'needs_clarification';
    case AwaitingVaultSeal = 'awaiting_vault_seal';
    case Ready = 'ready';
    case Failed = 'failed';
}
