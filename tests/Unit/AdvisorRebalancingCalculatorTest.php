<?php

use App\Services\Advisor\AdvisorRebalancingCalculator;

function rebalancingContext(bool $hasUnpricedAssets = false): array
{
    return [
        'recommendation_mode' => 'rebalance',
        'new_capital' => ['amount' => 200, 'currency' => 'usd'],
        'current_portfolio' => [
            'base_currency' => 'usd',
            'total_value' => $hasUnpricedAssets ? null : 800,
            'priced_subtotal' => 800,
            'has_unpriced_assets' => $hasUnpricedAssets,
            'holdings' => [
                [
                    'asset_key' => 'cash', 'name' => 'Cash', 'current_value' => 600,
                    'current_percent' => $hasUnpricedAssets ? null : 75, 'price_available' => true,
                ],
                [
                    'asset_key' => 'bitcoin', 'name' => 'Bitcoin',
                    'current_value' => $hasUnpricedAssets ? null : 200,
                    'current_percent' => $hasUnpricedAssets ? null : 25,
                    'price_available' => ! $hasUnpricedAssets,
                ],
            ],
        ],
    ];
}

function rebalancingRecommendation(): array
{
    return ['primary' => ['allocations' => [
        ['asset_key' => 'cash', 'target_percent' => 70],
        ['asset_key' => 'bitcoin', 'target_percent' => 30],
    ], 'options_overlays' => [['maximum_risk_budget_percent' => 2]]]];
}

test('current holdings and new capital produce exact target differences', function () {
    $plan = app(AdvisorRebalancingCalculator::class)->calculate(rebalancingContext(), rebalancingRecommendation());

    expect($plan['combined_capital'])->toBe(1000.0)
        ->and($plan['exact_amounts_available'])->toBeTrue()
        ->and($plan['rows'][0])->toMatchArray([
            'asset_key' => 'cash',
            'current_percent' => 75.0,
            'target_percent' => 70,
            'target_value' => 700.0,
            'difference' => 100.0,
        ])
        ->and($plan['rows'][1]['target_value'])->toBe(300.0)
        ->and($plan['rows'][1]['difference'])->toBe(100.0);
});

test('missing prices suppress exact monetary guidance without changing target percentages', function () {
    $plan = app(AdvisorRebalancingCalculator::class)->calculate(rebalancingContext(true), rebalancingRecommendation());

    expect($plan['combined_capital'])->toBeNull()
        ->and($plan['exact_amounts_available'])->toBeFalse()
        ->and($plan['rows'][0]['target_percent'])->toBe(70)
        ->and($plan['rows'][0]['target_value'])->toBeNull()
        ->and($plan['rows'][0]['difference'])->toBeNull()
        ->and($plan['rows'][1]['current_value'])->toBeNull()
        ->and($plan['rows'][1]['difference'])->toBeNull();
});

test('target only mode never creates a current to target comparison', function () {
    $context = rebalancingContext();
    $context['recommendation_mode'] = 'target_only';
    $context['current_portfolio'] = null;

    expect(app(AdvisorRebalancingCalculator::class)->calculate($context, rebalancingRecommendation()))->toBeNull();
});
