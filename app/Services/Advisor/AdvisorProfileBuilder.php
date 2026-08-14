<?php

namespace App\Services\Advisor;

use App\Models\InvestmentAsset;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AdvisorProfileBuilder
{
    public function __construct(
        private readonly InvestorProfileScorer $scorer,
        private readonly AdvisorAssessmentDefinition $definition,
        private readonly InvestorPersonaResolver $personaResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function build(array $answers, User $user): array
    {
        $result = $this->scorer->score($answers);
        $preferences = (array) $answers['portfolio_preferences'];
        $options = $this->optionsCapability((array) $answers['options_capability']);
        $riskEnvelope = $this->definition->all()['risk_envelopes'][$result['risk_band']];
        $userMaximum = (int) ($preferences['maximum_single_asset_allocation'] ?? 50);

        return [
            'profile_version' => 1,
            'persona' => $result['persona'],
            'risk_band' => $result['risk_band'],
            'scores' => $result['scores'],
            'maximum_tolerated_drawdown' => $result['maximum_tolerated_drawdown'],
            'financial_context' => [
                'income_stability' => $answers['q2_income_stability'],
                'emergency_fund' => $answers['q3_emergency_fund'],
                'high_interest_debt' => $answers['q4_high_interest_debt'],
                'portfolio_share_of_liquid_wealth' => $answers['q5_portfolio_wealth_share'],
            ],
            'loss_context' => [
                'permanent_loss_impact' => $answers['q11_permanent_loss_impact'],
                'drawdown_10_response' => $answers['q12_drawdown_10'],
                'drawdown_20_response' => $answers['q13_drawdown_20'],
                'drawdown_35_response' => $answers['q14_drawdown_35'],
            ],
            'goals' => [
                'primary' => $answers['q6_primary_goal'],
                'importance' => $answers['q7_goal_importance'],
                'time_horizon' => $answers['q8_time_horizon'],
                'early_withdrawal_likelihood' => $answers['q9_early_withdrawal'],
                'liquidity' => $answers['q10_liquidity'],
                'target_return' => $answers['q22_target_return'],
            ],
            'portfolio_preferences' => [
                'scope' => $preferences['scope'],
                'primary_currency' => strtoupper((string) $preferences['primary_currency']),
                'country' => $this->cleanText($preferences['country'] ?? null, 80),
                'markets' => array_map(fn (mixed $market): string => $this->cleanText($market, 80), (array) $preferences['markets']),
                'tax_sensitive' => (bool) $preferences['tax_sensitive'],
            ],
            'selected_assets' => array_map(fn (array $asset): array => $this->safeAsset($asset, $user), $preferences['assets']),
            'options_capability' => $options,
            'constraints' => [
                'minimum_liquid_allocation' => $this->minimumLiquidAllocation((int) $result['scores']['liquidity_need']),
                'maximum_single_asset_allocation' => min($userMaximum, $riskEnvelope['single_asset_max']),
                'maximum_high_risk_allocation' => $riskEnvelope['high_risk_max'],
                'maximum_speculative_allocation' => $riskEnvelope['speculative_max'],
                'maximum_options_risk_budget' => $options['maximum_risk_budget_percent'],
                'hard_caps' => $result['hard_caps'],
            ],
            'warnings' => $result['warnings'],
        ];
    }

    /**
     * Canonicalize the browser-derived profile used while Private Vault is armed.
     *
     * The server cannot read the raw answers, but it can still recalculate every
     * risk ceiling from the normalized dimensions and discard unexpected fields.
     *
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    public function normalizeDerivedProfile(array $profile, User $user): array
    {
        $scores = collect([
            'risk_willingness', 'risk_capacity', 'financial_resilience', 'liquidity_need',
            'investment_knowledge', 'behavioral_stability', 'loss_aversion',
            'return_ambition', 'time_horizon',
        ])->mapWithKeys(fn (string $key): array => [$key => (int) $profile['scores'][$key]])->all();
        $financial = (array) $profile['financial_context'];
        $loss = (array) $profile['loss_context'];
        $goals = (array) $profile['goals'];
        $maximumDrawdown = (int) $profile['maximum_tolerated_drawdown'];
        $hardCaps = $this->derivedHardCaps($financial, $loss, $goals, $scores, $maximumDrawdown);
        $weights = $this->definition->all()['weights'];
        $rawRisk = collect($weights)->sum(fn (float|int $weight, string $dimension): float => $scores[$dimension] * $weight);
        $capacityCeiling = 20 + ($scores['risk_capacity'] * 0.8);
        $willingnessCeiling = 20 + ($scores['risk_willingness'] * 0.8);
        $scores['raw_risk'] = (int) round($rawRisk);
        $scores['capacity_ceiling'] = (int) round($capacityCeiling);
        $scores['willingness_ceiling'] = (int) round($willingnessCeiling);
        $scores['effective_risk'] = (int) round(min($rawRisk, $capacityCeiling, $willingnessCeiling, ...array_column($hardCaps, 'ceiling')));
        $riskBand = $this->riskBand($scores['effective_risk']);
        $preferences = (array) $profile['portfolio_preferences'];
        $options = $this->normalizeDerivedOptions((array) $profile['options_capability']);
        $envelope = $this->definition->all()['risk_envelopes'][$riskBand];
        $requestedMaximum = (int) ($profile['constraints']['maximum_single_asset_allocation'] ?? $envelope['single_asset_max']);

        return [
            'profile_version' => 1,
            'persona' => $this->personaResolver->resolve($scores),
            'risk_band' => $riskBand,
            'scores' => $scores,
            'maximum_tolerated_drawdown' => $maximumDrawdown,
            'financial_context' => [
                'income_stability' => $financial['income_stability'],
                'emergency_fund' => $financial['emergency_fund'],
                'high_interest_debt' => $financial['high_interest_debt'],
                'portfolio_share_of_liquid_wealth' => $financial['portfolio_share_of_liquid_wealth'],
            ],
            'loss_context' => [
                'permanent_loss_impact' => $loss['permanent_loss_impact'],
                'drawdown_10_response' => $loss['drawdown_10_response'],
                'drawdown_20_response' => $loss['drawdown_20_response'],
                'drawdown_35_response' => $loss['drawdown_35_response'],
            ],
            'goals' => [
                'primary' => $goals['primary'],
                'importance' => $goals['importance'],
                'time_horizon' => $goals['time_horizon'],
                'early_withdrawal_likelihood' => $goals['early_withdrawal_likelihood'],
                'liquidity' => [
                    'proportion' => $goals['liquidity']['proportion'],
                    'speed' => $goals['liquidity']['speed'],
                ],
                'target_return' => $goals['target_return'],
            ],
            'portfolio_preferences' => [
                'scope' => $preferences['scope'],
                'primary_currency' => strtoupper($this->cleanText($preferences['primary_currency'], 12)),
                'country' => $this->cleanText($preferences['country'] ?? null, 80),
                'markets' => array_map(fn (mixed $market): string => $this->cleanText($market, 80), (array) $preferences['markets']),
                'tax_sensitive' => (bool) $preferences['tax_sensitive'],
            ],
            'selected_assets' => array_map(fn (array $asset): array => $this->safeAsset($asset, $user), (array) $profile['selected_assets']),
            'options_capability' => $options,
            'constraints' => [
                'minimum_liquid_allocation' => $this->minimumLiquidAllocation($scores['liquidity_need']),
                'maximum_single_asset_allocation' => min(max(5, $requestedMaximum), $envelope['single_asset_max']),
                'maximum_high_risk_allocation' => $envelope['high_risk_max'],
                'maximum_speculative_allocation' => $envelope['speculative_max'],
                'maximum_options_risk_budget' => $options['maximum_risk_budget_percent'],
                'hard_caps' => $hardCaps,
            ],
            'warnings' => $this->derivedWarnings($goals, $scores),
        ];
    }

    /** @param array<string, mixed> $asset
     * @return array<string, mixed>
     */
    private function safeAsset(array $asset, User $user): array
    {
        if (($asset['source'] ?? null) === 'cashpilot') {
            $supported = InvestmentAsset::query()
                ->availableFor($user)
                ->find($asset['investment_asset_id'] ?? null);

            if ($supported === null) {
                throw ValidationException::withMessages(['selected_assets' => __('advisor.validation.unknown_asset')]);
            }

            $classification = $this->classifySupportedAsset($supported->slug);
            $asset = [
                ...$asset,
                'asset_key' => 'cashpilot-'.$supported->id,
                'investment_asset_id' => $supported->id,
                'name' => $supported->label(),
                'ticker' => strtoupper($supported->slug),
                'identifier' => null,
                'exchange_or_market' => null,
                'country' => null,
                ...$classification,
            ];
        }

        return [
            'asset_key' => $this->cleanText($asset['asset_key'], 80),
            'source' => $asset['source'],
            'investment_asset_id' => $asset['source'] === 'cashpilot' ? ($asset['investment_asset_id'] ?? null) : null,
            'name' => $this->cleanText($asset['name'], 120),
            'ticker' => $this->cleanText($asset['ticker'] ?? null, 30),
            'identifier' => $this->cleanText($asset['identifier'] ?? null, 80),
            'exchange_or_market' => $this->cleanText($asset['exchange_or_market'] ?? null, 80),
            'country' => $this->cleanText($asset['country'] ?? null, 80),
            'currency' => strtoupper($this->cleanText($asset['currency'], 12)),
            'category' => $asset['category'],
            'risk_band' => $asset['risk_band'],
            'liquidity' => $asset['liquidity'],
            'perspective' => $asset['perspective'],
            'conviction' => $asset['conviction'],
            'holding_period' => $asset['holding_period'],
            'inclusion' => $asset['inclusion'],
        ];
    }

    /** @return array<string, string> */
    private function classifySupportedAsset(string $slug): array
    {
        return match ($slug) {
            'gold', 'silver' => ['category' => 'metal', 'risk_band' => 'moderate', 'liquidity' => 'same_day', 'currency' => 'USD'],
            'bitcoin', 'ethereum' => ['category' => 'crypto', 'risk_band' => 'speculative', 'liquidity' => 'same_day', 'currency' => 'USD'],
            'usd', 'eur' => ['category' => 'currency', 'risk_band' => 'defensive', 'liquidity' => 'same_day', 'currency' => strtoupper($slug)],
            default => ['category' => 'other', 'risk_band' => 'unknown', 'liquidity' => 'within_week', 'currency' => 'USD'],
        };
    }

    /** @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function optionsCapability(array $options): array
    {
        if (($options['willingness'] ?? 'no') === 'no') {
            return [
                'willingness' => 'no',
                'knowledge_score' => 0,
                'experience_level' => 'none',
                'allowed_underlying_categories' => [],
                'allowed_strategy_families' => [],
                'maximum_risk_budget_percent' => 0,
                'monitoring_suitability' => 'not_applicable',
            ];
        }

        $experienceScores = ['none' => 0, 'under_1' => 25, '1_3' => 60, '3_plus' => 90];
        $tradeScores = ['none' => 0, '1_10' => 25, '11_50' => 65, '50_plus' => 100];
        $knowledgeAnswers = (array) ($options['knowledge_answers'] ?? []);
        $knowledgeScore = (int) round((($experienceScores[$options['experience_years']] ?? 0) * 0.35)
            + (($tradeScores[$options['trade_count']] ?? 0) * 0.35)
            + ((count(array_filter($knowledgeAnswers)) / 3 * 100) * 0.30));
        $experienceLevel = match (true) {
            $knowledgeScore >= 70 => 'advanced',
            $knowledgeScore >= 40 => 'intermediate',
            default => 'beginner',
        };
        $strategies = [];

        if (($options['broker_access'] ?? false) && $knowledgeScore >= 20) {
            $strategies[] = 'protective_put';
        }

        if (($options['broker_access'] ?? false) && ($options['cap_upside'] ?? false) && $knowledgeScore >= 30) {
            $strategies[] = 'collar';
        }

        if (($options['broker_access'] ?? false) && ($options['assignment_tolerance'] ?? false) && $knowledgeScore >= 40) {
            $strategies[] = 'covered_call';
        }

        if (($options['broker_access'] ?? false) && $knowledgeScore >= 50 && in_array($options['monitoring'] ?? null, ['daily', 'weekly'], true)) {
            $strategies[] = 'vertical_debit_spread';
        }

        if (($options['broker_access'] ?? false) && $knowledgeScore >= 70 && in_array($options['monitoring'] ?? null, ['daily', 'weekly'], true)) {
            $strategies[] = 'defined_risk_vertical_credit_spread';
        }

        $riskCap = match ($experienceLevel) {
            'advanced' => 10,
            'intermediate' => 5,
            default => 2,
        };

        return [
            'willingness' => $options['willingness'],
            'broker_access' => (bool) ($options['broker_access'] ?? false),
            'knowledge_score' => $knowledgeScore,
            'experience_level' => $experienceLevel,
            'objective' => $options['objective'] ?? null,
            'allowed_underlying_categories' => array_values((array) ($options['approved_underlyings'] ?? [])),
            'allowed_strategy_families' => $strategies,
            'maximum_risk_budget_percent' => ($options['broker_access'] ?? false)
                ? min((float) ($options['maximum_risk_budget_percent'] ?? 0), $riskCap)
                : 0,
            'recurring_premium' => (bool) ($options['recurring_premium'] ?? false),
            'cap_upside' => (bool) ($options['cap_upside'] ?? false),
            'assignment_tolerance' => (bool) ($options['assignment_tolerance'] ?? false),
            'monitoring_suitability' => $options['monitoring'] ?? 'rarely',
        ];
    }

    /** @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function normalizeDerivedOptions(array $options): array
    {
        if (($options['willingness'] ?? 'no') === 'no') {
            return [
                'willingness' => 'no',
                'knowledge_score' => 0,
                'experience_level' => 'none',
                'allowed_underlying_categories' => [],
                'allowed_strategy_families' => [],
                'maximum_risk_budget_percent' => 0,
                'monitoring_suitability' => 'not_applicable',
            ];
        }

        $knowledgeScore = max(0, min(100, (int) ($options['knowledge_score'] ?? 0)));
        $experienceLevel = match (true) {
            $knowledgeScore >= 70 => 'advanced',
            $knowledgeScore >= 40 => 'intermediate',
            default => 'beginner',
        };
        $brokerAccess = (bool) ($options['broker_access'] ?? false);
        $capUpside = (bool) ($options['cap_upside'] ?? false);
        $assignmentTolerance = (bool) ($options['assignment_tolerance'] ?? false);
        $monitoring = in_array($options['monitoring_suitability'] ?? null, ['daily', 'weekly', 'monthly', 'rarely'], true)
            ? $options['monitoring_suitability']
            : 'rarely';
        $strategies = [];

        if ($brokerAccess && $knowledgeScore >= 20) {
            $strategies[] = 'protective_put';
        }
        if ($brokerAccess && $capUpside && $knowledgeScore >= 30) {
            $strategies[] = 'collar';
        }
        if ($brokerAccess && $assignmentTolerance && $knowledgeScore >= 40) {
            $strategies[] = 'covered_call';
        }
        if ($brokerAccess && $knowledgeScore >= 50 && in_array($monitoring, ['daily', 'weekly'], true)) {
            $strategies[] = 'vertical_debit_spread';
        }
        if ($brokerAccess && $knowledgeScore >= 70 && in_array($monitoring, ['daily', 'weekly'], true)) {
            $strategies[] = 'defined_risk_vertical_credit_spread';
        }

        $riskCap = match ($experienceLevel) {
            'advanced' => 10,
            'intermediate' => 5,
            default => 2,
        };
        $allowedUnderlyings = array_values(array_intersect(
            (array) ($options['allowed_underlying_categories'] ?? []),
            ['stocks', 'etfs', 'indices', 'commodities', 'currencies', 'crypto'],
        ));

        return [
            'willingness' => in_array($options['willingness'] ?? null, ['yes', 'not_sure'], true) ? $options['willingness'] : 'not_sure',
            'broker_access' => $brokerAccess,
            'knowledge_score' => $knowledgeScore,
            'experience_level' => $experienceLevel,
            'objective' => in_array($options['objective'] ?? null, ['downside_hedging', 'income', 'defined_risk_growth', 'combination'], true)
                ? $options['objective']
                : 'downside_hedging',
            'allowed_underlying_categories' => $allowedUnderlyings,
            'allowed_strategy_families' => $strategies,
            'maximum_risk_budget_percent' => $brokerAccess
                ? min(max(0, (float) ($options['maximum_risk_budget_percent'] ?? 0)), $riskCap)
                : 0,
            'recurring_premium' => (bool) ($options['recurring_premium'] ?? false),
            'cap_upside' => $capUpside,
            'assignment_tolerance' => $assignmentTolerance,
            'monitoring_suitability' => $monitoring,
        ];
    }

    /**
     * @param  array<string, mixed>  $financial
     * @param  array<string, mixed>  $loss
     * @param  array<string, mixed>  $goals
     * @param  array<string, int>  $scores
     * @return array<int, array{reason: string, ceiling: int}>
     */
    private function derivedHardCaps(array $financial, array $loss, array $goals, array $scores, int $maximumDrawdown): array
    {
        $caps = [['reason' => 'maximum_tolerated_drawdown', 'ceiling' => min(100, 10 + ($maximumDrawdown * 2))]];

        if (($financial['high_interest_debt'] ?? null) === 'significant') {
            $caps[] = ['reason' => 'significant_high_interest_debt', 'ceiling' => 25];
        }
        if (($financial['emergency_fund'] ?? null) === 'under_1') {
            $caps[] = ['reason' => 'insufficient_emergency_savings', 'ceiling' => 25];
        }

        $horizonCaps = ['under_1_year' => 20, '1_3_years' => 35, '3_5_years' => 55];
        if (isset($horizonCaps[$goals['time_horizon'] ?? ''])) {
            $caps[] = ['reason' => 'short_time_horizon', 'ceiling' => $horizonCaps[$goals['time_horizon']]];
        }
        if (($goals['importance'] ?? null) === 'essential') {
            $caps[] = ['reason' => 'essential_goal', 'ceiling' => 35];
        }
        if (($financial['portfolio_share_of_liquid_wealth'] ?? null) === 'over_75') {
            $caps[] = ['reason' => 'high_portfolio_dependency', 'ceiling' => 35];
        }
        if (($loss['permanent_loss_impact'] ?? null) === 'severe') {
            $caps[] = ['reason' => 'severe_loss_consequence', 'ceiling' => 20];
        }
        if ($scores['behavioral_stability'] < 30) {
            $caps[] = ['reason' => 'low_behavioral_stability', 'ceiling' => 35];
        }

        return $caps;
    }

    /** @param array<string, mixed> $goals
     * @param  array<string, int>  $scores
     * @return array<int, string>
     */
    private function derivedWarnings(array $goals, array $scores): array
    {
        $warnings = [];

        if (($goals['target_return'] ?? null) === '18_plus' && $scores['effective_risk'] < 65) {
            $warnings[] = 'return_expectation_exceeds_risk_capacity';
        }
        if ($scores['risk_willingness'] > $scores['risk_capacity'] + 20) {
            $warnings[] = 'willingness_exceeds_capacity';
        }
        if ($scores['risk_capacity'] > $scores['risk_willingness'] + 20) {
            $warnings[] = 'capacity_exceeds_willingness';
        }

        return $warnings;
    }

    private function riskBand(int $effectiveRisk): string
    {
        return match (true) {
            $effectiveRisk <= 20 => 'very_conservative',
            $effectiveRisk <= 40 => 'conservative',
            $effectiveRisk <= 60 => 'balanced',
            $effectiveRisk <= 80 => 'growth',
            default => 'aggressive',
        };
    }

    private function minimumLiquidAllocation(int $liquidityNeed): int
    {
        return match (true) {
            $liquidityNeed >= 80 => 50,
            $liquidityNeed >= 60 => 35,
            $liquidityNeed >= 40 => 20,
            $liquidityNeed >= 20 => 10,
            default => 5,
        };
    }

    private function cleanText(mixed $value, int $maximumLength): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', trim((string) $value)) ?? '';

        return mb_substr($clean, 0, $maximumLength);
    }
}
