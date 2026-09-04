<?php

use App\Enums\Feature;

return [
    'enabled' => env('MILES_ENABLED', true),
    'advisor_charging' => env('MILES_ADVISOR_CHARGING', false),
    'cosmetics_enabled' => env('MILES_COSMETICS_ENABLED', true),
    'gifting_enabled' => env('MILES_GIFTING_ENABLED', true),

    'welcome_grant' => 150,
    'unlock_price' => 25,
    'paid_modules' => [
        Feature::Bills->value,
        Feature::Budgets->value,
        Feature::Investments->value,
        Feature::Portfolio->value,
        Feature::Goals->value,
        Feature::AiAssistant->value,
        Feature::TelegramBot->value,
    ],
    'daily_claims' => [3, 3, 3, 4, 4, 5, 8],
    'daily_activity_reward' => 2,
    'streak_freeze_price' => 40,
    'streak_freeze_maximum' => 2,
    'streak_repair_price' => 100,
    'streak_repair_days' => 7,
    'streak_repairs_per_month' => 2,

    'cosmetics' => [
        'callsign_pathfinder' => ['type' => 'callsign', 'label' => 'Pathfinder', 'price' => 60],
        'badge_emerald_wings' => ['type' => 'badge', 'label' => 'Emerald Wings', 'price' => 75],
        'palette_horizon' => ['type' => 'palette', 'label' => 'Horizon palette', 'price' => 80],
        'theme_night_flight' => ['type' => 'theme', 'label' => 'Night Flight', 'price' => 120],
    ],
    'advisor' => [
        'first_recommendation' => 175,
        'recommendation' => 250,
        'assessment' => 25,
        'assessment_free_days' => 30,
        'guidance' => 80,
        'consultation' => 15,
        'provider_rates' => [],
    ],
    'referrals' => [
        'rolling_cap' => 1000,
        'gift_monthly_cap' => 200,
        'gift_per_friend_cap' => 100,
        'gift_amounts' => [25, 50],
        'stages' => [
            'activated' => ['days' => 3, 'within_days' => 14, 'referrer' => 25, 'friend' => 15],
            'retained' => ['days' => 7, 'within_days' => 30, 'referrer' => 25, 'friend' => 15],
            'habit' => ['days' => 30, 'within_days' => 90, 'referrer' => 50, 'friend' => 15],
        ],
    ],

    /* Rewards recognize actions and consistency, never financial amounts. */
    'never_reward_financial_amounts' => true,
];
