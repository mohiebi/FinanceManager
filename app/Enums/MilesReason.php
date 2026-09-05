<?php

namespace App\Enums;

enum MilesReason: string
{
    case Welcome = 'welcome';
    case DailyClaim = 'daily_claim';
    case DailyActivity = 'daily_activity';
    case Milestone = 'milestone';
    case ModuleUnlock = 'module_unlock';
    case StreakFreeze = 'streak_freeze';
    case StreakRepair = 'streak_repair';
    case Cosmetic = 'cosmetic';
    case Referral = 'referral';
    case ReferralGiftSent = 'referral_gift_sent';
    case ReferralGiftReceived = 'referral_gift_received';
    case AdvisorReservation = 'advisor_reservation';
    case AdvisorRefund = 'advisor_refund';
    case AdminAdjustment = 'admin_adjustment';
    case LegacyProConversion = 'legacy_pro_conversion';
    case SubscriptionRefill = 'subscription_refill';
    case PackPurchase = 'pack_purchase';
    case GiftCode = 'gift_code';
    case PromoDrop = 'promo_drop';
}
