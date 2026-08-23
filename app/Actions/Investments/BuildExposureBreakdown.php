<?php

namespace App\Actions\Investments;

use App\Enums\AssetClass;

/**
 * Collapses per-asset holdings into the markets they are actually bets on.
 *
 * Written for the advisor and the MCP tools rather than the screen: a portfolio
 * listing "نیم سکه غیر بانکی", "Gold" and "ربع سکه" as separate lines reads to a
 * model as three positions, and the advice that follows is to diversify into
 * gold — which the user already is, four times over.
 *
 * Rolls up **value only**, never quantity. A half coin is measured in coins and
 * bullion in grams; "4 coin + 14 g" is not a quantity. Where an asset states how
 * much of the underlying one unit is worth, a separate equivalent quantity is
 * derived — that one is real, and is the number a metals holder actually thinks in.
 */
class BuildExposureBreakdown
{
    /**
     * @param  array<int, array<string, mixed>>  $assets  rows from {@see BuildPortfolioBreakdown::handle()}
     * @param  callable(float): string  $formatter
     * @return array{exposures: list<array<string, mixed>>, classes: list<array<string, mixed>>, has_unpriced_assets: bool}
     */
    public function handle(array $assets, callable $formatter): array
    {
        $total = array_sum(array_map(
            fn (array $asset): float => (float) $asset['current_value'],
            $assets,
        ));

        $hasUnpricedAssets = collect($assets)->contains(
            fn (array $asset): bool => ! ($asset['price_available'] ?? true),
        );

        return [
            'exposures' => $this->exposures($assets, $formatter, $total, $hasUnpricedAssets),
            'classes' => $this->classes($assets, $formatter, $total, $hasUnpricedAssets),
            // Percentages are shares of a total that is missing the unpriced
            // rows, so they overstate everything that is priced. Flagged rather
            // than hidden, so a reader knows which way the error runs.
            'has_unpriced_assets' => $hasUnpricedAssets,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $assets
     * @param  callable(float): string  $formatter
     * @return list<array<string, mixed>>
     */
    private function exposures(array $assets, callable $formatter, float $total, bool $hasUnpricedAssets): array
    {
        $exposures = collect($assets)
            ->groupBy(fn (array $asset): int => (int) ($asset['exposure_id'] ?? $asset['id']))
            ->map(function ($members, int $exposureId) use ($formatter, $total, $hasUnpricedAssets): array {
                // The row that *is* the market, when it is held directly. When it
                // is not — gold owned only as coins — the members all name it, so
                // the label comes from the link instead.
                $root = $members->firstWhere('id', $exposureId);
                $viaLink = $members->first(fn (array $asset): bool => filled($asset['underlying_label'] ?? null));
                $value = (float) $members->sum(fn (array $asset): float => (float) $asset['current_value']);

                return [
                    'exposure_id' => $exposureId,
                    'label' => $root['label'] ?? $viaLink['underlying_label'] ?? '',
                    'asset_class' => $root['asset_class'] ?? $members->first()['asset_class'] ?? null,
                    'value' => $value,
                    'value_formatted' => $formatter($value),
                    'percent' => $this->share($value, $total, $hasUnpricedAssets),
                    'equivalent_quantity' => $this->equivalentQuantity($members->all()),
                    'equivalent_unit' => $root['unit'] ?? $viaLink['underlying_unit'] ?? null,
                    'is_held_directly' => $root !== null,
                    'members' => $members
                        ->map(fn (array $asset): array => [
                            'id' => $asset['id'],
                            'label' => $asset['label'],
                            'slug' => $asset['key'],
                            'quantity' => $asset['quantity'],
                            'unit' => $asset['unit'],
                            'value' => (float) $asset['current_value'],
                            'value_formatted' => $asset['current_value_formatted'],
                            'underlying_ratio' => $asset['underlying_ratio'] ?? null,
                            'price_available' => $asset['price_available'] ?? true,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        usort($exposures, fn (array $left, array $right): int => $right['value'] <=> $left['value']);

        return $exposures;
    }

    /**
     * @param  array<int, array<string, mixed>>  $assets
     * @param  callable(float): string  $formatter
     * @return list<array<string, mixed>>
     */
    private function classes(array $assets, callable $formatter, float $total, bool $hasUnpricedAssets): array
    {
        $classes = collect($assets)
            ->groupBy(fn (array $asset): string => (string) ($asset['asset_class'] ?? 'unclassified'))
            ->map(function ($members, string $key) use ($formatter, $total, $hasUnpricedAssets): array {
                $value = (float) $members->sum(fn (array $asset): float => (float) $asset['current_value']);
                $class = AssetClass::tryFrom($key);

                return [
                    'key' => $key,
                    'label' => $class?->label() ?? __('finance.asset_classes.unclassified'),
                    'value' => $value,
                    'value_formatted' => $formatter($value),
                    'percent' => $this->share($value, $total, $hasUnpricedAssets),
                    'asset_count' => $members->count(),
                    'exposure_count' => $members
                        ->pluck('exposure_id')
                        ->unique()
                        ->count(),
                ];
            })
            ->values()
            ->all();

        usort($classes, fn (array $left, array $right): int => $right['value'] <=> $left['value']);

        return $classes;
    }

    /**
     * How much of the underlying the group adds up to, in the underlying's unit.
     *
     * Null unless every member can be converted — a group where one bar states
     * its gram weight and another does not would otherwise report a total that
     * looks complete and is not. A directly held root counts as itself.
     *
     * @param  array<int, array<string, mixed>>  $members
     */
    private function equivalentQuantity(array $members): ?float
    {
        $total = 0.0;

        foreach ($members as $member) {
            $ratio = $member['underlying_ratio'] ?? null;

            if ($member['underlying_asset_id'] === null) {
                $total += (float) $member['quantity'];

                continue;
            }

            if ($ratio === null) {
                return null;
            }

            $total += (float) $member['quantity'] * (float) $ratio;
        }

        return round($total, 8);
    }

    private function share(float $value, float $total, bool $hasUnpricedAssets): ?float
    {
        if ($hasUnpricedAssets || $total <= 0) {
            return null;
        }

        return round(($value / $total) * 100, 2);
    }
}
