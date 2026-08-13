<?php

namespace App\Services\Advisor;

use Illuminate\Support\Collection;

class AdvisorProposalValidator
{
    private const PROHIBITED_STRATEGY_TERMS = ['naked', 'uncovered', 'ratio', 'unlimited', 'undefined_loss', 'short_straddle', 'short_strangle'];

    /**
     * @param  array<string, mixed>  $proposal
     * @param  array<string, mixed>  $context
     * @return array<int, array{code: string, path: string, message: string}>
     */
    public function validate(array $proposal, array $context): array
    {
        if (($proposal['status'] ?? null) !== 'recommendation_ready') {
            return [['code' => 'invalid_status', 'path' => 'status', 'message' => 'A completed proposal must use recommendation_ready.']];
        }

        $violations = $this->validateKnowledgeBoundaries($proposal, $context);
        $violations = [...$violations, ...$this->validatePortfolio((array) ($proposal['primary'] ?? []), $context, 'primary')];
        $primaryRisk = $this->riskLoad((array) ($proposal['primary']['allocations'] ?? []), $context);

        if (is_array($proposal['safer_alternative'] ?? null) && ($proposal['safer_alternative']['allocations'] ?? []) !== []) {
            $violations = [...$violations, ...$this->validatePortfolio($proposal['safer_alternative'], $context, 'safer_alternative')];
            $saferRisk = $this->riskLoad($proposal['safer_alternative']['allocations'], $context);
            if ($saferRisk >= $primaryRisk - 5) {
                $violations[] = $this->violation('safer_not_meaningfully_safer', 'safer_alternative.allocations', 'The safer alternative must materially reduce the allocation risk load.');
            }
        } else {
            $violations[] = $this->violation('missing_safer_alternative', 'safer_alternative', 'A safer alternative is required.');
        }

        $higherRisk = (array) ($proposal['higher_risk_alternative'] ?? []);
        if (($higherRisk['available'] ?? false) === true) {
            $violations = [...$violations, ...$this->validatePortfolio($higherRisk, $context, 'higher_risk_alternative')];
            if ($this->riskLoad((array) ($higherRisk['allocations'] ?? []), $context) <= $primaryRisk) {
                $violations[] = $this->violation('higher_not_higher_risk', 'higher_risk_alternative.allocations', 'The higher-risk alternative must have a greater risk load than the primary plan.');
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $portfolio
     * @param  array<string, mixed>  $context
     * @return array<int, array{code: string, path: string, message: string}>
     */
    private function validatePortfolio(array $portfolio, array $context, string $path): array
    {
        $violations = [];
        $selected = collect($context['selected_assets'] ?? [])->keyBy('asset_key');
        $allocations = (array) ($portfolio['allocations'] ?? []);
        $seen = [];
        $total = 0;

        foreach ($allocations as $index => $allocation) {
            $assetKey = $allocation['asset_key'] ?? null;
            $percent = $allocation['target_percent'] ?? null;
            $allocationPath = "{$path}.allocations.{$index}";

            if (! is_string($assetKey) || ! $selected->has($assetKey)) {
                $violations[] = $this->violation('unselected_asset', "{$allocationPath}.asset_key", 'Every allocation must reference a user-selected asset_key.');

                continue;
            }

            if (isset($seen[$assetKey])) {
                $violations[] = $this->violation('duplicate_asset', "{$allocationPath}.asset_key", 'Each asset_key may appear only once.');
            }
            $seen[$assetKey] = true;

            if (! is_int($percent) || $percent < 0 || $percent > 100) {
                $violations[] = $this->violation('invalid_percentage', "{$allocationPath}.target_percent", 'Allocation percentages must be integers from 0 through 100.');

                continue;
            }

            $total += $percent;
            $maximum = (int) $context['constraints']['maximum_single_asset_allocation'];
            if ($percent > $maximum) {
                $violations[] = $this->violation('excessive_concentration', "{$allocationPath}.target_percent", "No asset may exceed {$maximum}%.");
            }

            $asset = $selected->get($assetKey);
            if (($asset['risk_band'] ?? null) === 'unknown' && $percent > 10) {
                $violations[] = $this->violation('unknown_risk_too_large', "{$allocationPath}.target_percent", 'An unclassified custom asset may not exceed 10%.');
            }

            if ($this->isExcluded($asset, (array) ($context['portfolio_preferences']['exclusions'] ?? []))) {
                $violations[] = $this->violation('excluded_asset', "{$allocationPath}.asset_key", 'The allocation conflicts with an explicit user exclusion.');
            }
        }

        if ($total !== 100) {
            $violations[] = $this->violation('allocation_total', "{$path}.allocations", "Base allocations total {$total}%; they must total exactly 100%.");
        }

        foreach ($selected as $assetKey => $asset) {
            if (($asset['inclusion'] ?? null) === 'required' && (($this->percentFor($allocations, $assetKey)) <= 0)) {
                $violations[] = $this->violation('required_asset_missing', "{$path}.allocations", "Required asset {$assetKey} must receive a non-zero allocation.");
            }
        }

        $liquidPercent = $this->sumBy($allocations, $selected, fn (array $asset): bool => ($asset['liquidity'] ?? null) === 'same_day' || ($asset['category'] ?? null) === 'currency');
        $minimumLiquid = (int) $context['constraints']['minimum_liquid_allocation'];
        if ($liquidPercent < $minimumLiquid) {
            $violations[] = $this->violation('insufficient_liquidity', "{$path}.allocations", "Liquid assets must total at least {$minimumLiquid}%.");
        }

        $highRiskPercent = $this->sumBy($allocations, $selected, fn (array $asset): bool => in_array($asset['risk_band'] ?? null, ['growth', 'speculative'], true));
        $maximumHighRisk = (int) $context['constraints']['maximum_high_risk_allocation'];
        if ($highRiskPercent > $maximumHighRisk) {
            $violations[] = $this->violation('high_risk_envelope', "{$path}.allocations", "Growth and speculative assets may not exceed {$maximumHighRisk}%.");
        }

        $speculativePercent = $this->sumBy($allocations, $selected, fn (array $asset): bool => ($asset['risk_band'] ?? null) === 'speculative');
        $maximumSpeculative = (int) $context['constraints']['maximum_speculative_allocation'];
        if ($speculativePercent > $maximumSpeculative) {
            $violations[] = $this->violation('speculative_envelope', "{$path}.allocations", "Speculative assets may not exceed {$maximumSpeculative}%.");
        }

        return [...$violations, ...$this->validateOptions((array) ($portfolio['options_overlays'] ?? []), $allocations, $selected, $context, $path)];
    }

    /**
     * @param  array<int, mixed>  $overlays
     * @param  array<int, mixed>  $allocations
     * @param  Collection<string, array<string, mixed>>  $selected
     * @param  array<string, mixed>  $context
     * @return array<int, array{code: string, path: string, message: string}>
     */
    private function validateOptions(array $overlays, array $allocations, $selected, array $context, string $path): array
    {
        $violations = [];
        $capability = (array) $context['options_capability'];

        if ($overlays !== [] && (($capability['willingness'] ?? 'no') === 'no' || ! ($capability['broker_access'] ?? false))) {
            return [$this->violation('options_not_permitted', "{$path}.options_overlays", 'Options may only be used when the user is willing and has broker access.')];
        }

        foreach ($overlays as $index => $overlay) {
            $overlayPath = "{$path}.options_overlays.{$index}";
            $strategy = (string) ($overlay['strategy'] ?? '');
            $coveragePercent = $overlay['coverage_percent'] ?? null;

            if (! is_int($coveragePercent) || $coveragePercent < 0 || $coveragePercent > 100) {
                $violations[] = $this->violation('invalid_hedge_coverage', "{$overlayPath}.coverage_percent", 'Hedge coverage must be an integer percentage of the underlying exposure from 0 through 100.');
            }

            if ($this->containsProhibitedStrategy($strategy)) {
                $violations[] = $this->violation('prohibited_options_strategy', "{$overlayPath}.strategy", 'Naked, uncovered, ratio, undefined-loss, and unbounded strategies are prohibited.');
            }

            if (! in_array($strategy, (array) ($capability['allowed_strategy_families'] ?? []), true)) {
                $violations[] = $this->violation('unsupported_options_strategy', "{$overlayPath}.strategy", 'The strategy is outside the user’s derived options capability.');
            }

            $riskBudget = $overlay['maximum_risk_budget_percent'] ?? null;
            if (! is_numeric($riskBudget) || $riskBudget < 0 || $riskBudget > (float) ($capability['maximum_risk_budget_percent'] ?? 0)) {
                $violations[] = $this->violation('options_risk_budget', "{$overlayPath}.maximum_risk_budget_percent", 'The options risk budget exceeds the user’s derived maximum.');
            }

            if ($strategy === 'covered_call' && ! ($capability['assignment_tolerance'] ?? false)) {
                $violations[] = $this->violation('assignment_not_accepted', "{$overlayPath}.strategy", 'Covered calls require assignment tolerance.');
            }

            if ($strategy === 'collar' && ! ($capability['cap_upside'] ?? false)) {
                $violations[] = $this->violation('capped_upside_not_accepted', "{$overlayPath}.strategy", 'Collars require willingness to cap upside.');
            }

            foreach ((array) ($overlay['underlying_asset_keys'] ?? []) as $assetKey) {
                $asset = $selected->get($assetKey);
                if ($asset === null) {
                    $violations[] = $this->violation('unknown_options_underlying', "{$overlayPath}.underlying_asset_keys", 'Options underlyings must be selected assets.');

                    continue;
                }

                $category = $this->optionsCategory((string) $asset['category']);
                if ($category === null || ! in_array($category, (array) ($capability['allowed_underlying_categories'] ?? []), true)) {
                    $violations[] = $this->violation('options_category_not_allowed', "{$overlayPath}.underlying_asset_keys", 'The underlying category is not approved by the user.');
                }

                if ($this->percentFor($allocations, $assetKey) <= 0) {
                    $violations[] = $this->violation('options_underlying_not_allocated', "{$overlayPath}.underlying_asset_keys", 'An options overlay requires a non-zero base allocation to its underlying asset.');
                }
            }
        }

        return $violations;
    }

    /** @param array<int, mixed> $allocations */
    private function percentFor(array $allocations, string $assetKey): int
    {
        foreach ($allocations as $allocation) {
            if (($allocation['asset_key'] ?? null) === $assetKey) {
                return is_int($allocation['target_percent'] ?? null) ? $allocation['target_percent'] : 0;
            }
        }

        return 0;
    }

    /**
     * @param  array<int, mixed>  $allocations
     * @param  Collection<string, array<string, mixed>>  $selected
     */
    private function sumBy(array $allocations, $selected, callable $predicate): int
    {
        return array_reduce($allocations, function (int $total, array $allocation) use ($predicate, $selected): int {
            $asset = $selected->get($allocation['asset_key'] ?? '');

            return $asset !== null && $predicate($asset) ? $total + (int) ($allocation['target_percent'] ?? 0) : $total;
        }, 0);
    }

    /** @param array<int, mixed> $allocations
     * @param  array<string, mixed>  $context
     */
    private function riskLoad(array $allocations, array $context): float
    {
        $weights = ['defensive' => 0.1, 'moderate' => 0.35, 'growth' => 0.7, 'speculative' => 1.0, 'unknown' => 0.8];
        $selected = collect($context['selected_assets'] ?? [])->keyBy('asset_key');

        return array_reduce($allocations, function (float $total, array $allocation) use ($selected, $weights): float {
            $riskBand = $selected->get($allocation['asset_key'] ?? '')['risk_band'] ?? 'unknown';

            return $total + ((int) ($allocation['target_percent'] ?? 0) * $weights[$riskBand]);
        }, 0.0);
    }

    /** @param array<string, mixed> $asset
     * @param  array<int, string>  $exclusions
     */
    private function isExcluded(array $asset, array $exclusions): bool
    {
        $category = $asset['category'] ?? null;

        return ($category === 'crypto' && in_array('crypto', $exclusions, true))
            || ($category === 'private_asset' && in_array('private_assets', $exclusions, true));
    }

    private function containsProhibitedStrategy(string $strategy): bool
    {
        foreach (self::PROHIBITED_STRATEGY_TERMS as $term) {
            if (str_contains(mb_strtolower($strategy), $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $proposal
     * @param  array<string, mixed>  $context
     * @return array<int, array{code: string, path: string, message: string}>
     */
    private function validateKnowledgeBoundaries(array $proposal, array $context): array
    {
        if (($context['knowledge_mode'] ?? 'model_only') !== 'model_only') {
            return [];
        }

        $violations = [];
        $liveClaimPatterns = [
            '/\b(today(?:\'s)?|currently|right now)\b.{0,45}\b(price|market|trading|premium|volatility|yield|rate)\b/iu',
            '/\b(latest|recent)\b.{0,35}\b(news|earnings|economic|market|release|data)\b/iu',
            '/\bcurrent\b.{0,30}\b(option chain|price|premium|greeks?|liquidity|market conditions?)\b/iu',
            '/\bas of\b.{0,30}\b(20\d{2}|today|now)\b/iu',
        ];
        $contractFieldPatterns = ['strike', 'expiration', 'expiry', 'premium', 'delta', 'gamma', 'theta', 'vega', 'entry_price', 'contract_symbol'];

        $walk = function (mixed $value, string $path) use (&$walk, &$violations, $contractFieldPatterns, $liveClaimPatterns): void {
            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    $childPath = $path === '' ? (string) $key : $path.'.'.$key;
                    if (is_string($key) && in_array(mb_strtolower($key), $contractFieldPatterns, true)) {
                        $violations[] = $this->violation('live_options_detail', $childPath, 'Exact option contracts and live option-chain fields require retrieved market data.');
                    }
                    $walk($item, $childPath);
                }

                return;
            }

            if (! is_string($value)) {
                return;
            }

            foreach ($liveClaimPatterns as $pattern) {
                if (preg_match($pattern, $value) === 1) {
                    $violations[] = $this->violation('unsupported_current_market_claim', $path, 'Model-only recommendations cannot assert current prices, news, market conditions, or option-chain data.');

                    return;
                }
            }
        };

        $walk($proposal, '');

        return $violations;
    }

    private function optionsCategory(string $category): ?string
    {
        return match ($category) {
            'stock' => 'stocks',
            'etf' => 'etfs',
            'metal', 'commodity' => 'commodities',
            'currency' => 'currencies',
            'crypto' => 'crypto',
            default => null,
        };
    }

    /** @return array{code: string, path: string, message: string} */
    private function violation(string $code, string $path, string $message): array
    {
        return compact('code', 'path', 'message');
    }
}
