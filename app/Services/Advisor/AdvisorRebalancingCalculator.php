<?php

namespace App\Services\Advisor;

class AdvisorRebalancingCalculator
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $recommendation
     * @return array<string, mixed>|null
     */
    public function calculate(array $context, array $recommendation): ?array
    {
        if (($context['recommendation_mode'] ?? null) !== 'rebalance' || ! is_array($context['current_portfolio'] ?? null)) {
            return null;
        }

        $portfolio = $context['current_portfolio'];
        $newCapital = (float) ($context['new_capital']['amount'] ?? 0);
        $exactAmountsAvailable = ! $portfolio['has_unpriced_assets'] && is_numeric($portfolio['total_value']);
        $combinedCapital = $exactAmountsAvailable ? (float) $portfolio['total_value'] + $newCapital : null;
        $holdings = collect($portfolio['holdings'])->keyBy('asset_key');
        $rows = collect($recommendation['primary']['allocations'] ?? [])->map(function (array $allocation) use ($combinedCapital, $exactAmountsAvailable, $holdings): array {
            $holding = $holdings->get($allocation['asset_key']);
            $currentValue = $holding === null ? 0.0 : (isset($holding['current_value']) ? (float) $holding['current_value'] : null);
            $currentPercent = $holding === null ? 0.0 : (isset($holding['current_percent']) ? (float) $holding['current_percent'] : null);
            $targetValue = $exactAmountsAvailable
                ? round((float) $combinedCapital * ((int) $allocation['target_percent'] / 100), 2)
                : null;

            return [
                'asset_key' => $allocation['asset_key'],
                'current_percent' => $currentPercent,
                'target_percent' => (int) $allocation['target_percent'],
                'percentage_point_difference' => $currentPercent === null ? null : round((int) $allocation['target_percent'] - $currentPercent, 2),
                'current_value' => $currentValue,
                'target_value' => $targetValue,
                'difference' => $targetValue === null || $currentValue === null ? null : round($targetValue - $currentValue, 2),
                'price_available' => (bool) ($holding['price_available'] ?? true),
            ];
        })->values()->all();

        $unselected = collect($portfolio['holdings'])
            ->filter(fn (array $holding): bool => $holding['asset_key'] === null)
            ->map(fn (array $holding): array => [
                'name' => $holding['name'],
                'current_value' => $holding['current_value'],
                'target_percent' => 0,
                'difference' => $holding['current_value'] === null ? null : -$holding['current_value'],
                'price_available' => $holding['price_available'],
            ])->values()->all();

        return [
            'base_currency' => $portfolio['base_currency'],
            'combined_capital' => $combinedCapital,
            'rows' => $rows,
            'unselected_holdings' => $unselected,
            'exact_amounts_available' => $exactAmountsAvailable,
        ];
    }
}
