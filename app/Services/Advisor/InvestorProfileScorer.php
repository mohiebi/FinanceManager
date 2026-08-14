<?php

namespace App\Services\Advisor;

class InvestorProfileScorer
{
    public function __construct(
        private readonly AdvisorAssessmentDefinition $definition,
        private readonly InvestorPersonaResolver $personaResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function score(array $answers): array
    {
        $totals = [];
        $counts = [];
        $definition = $this->definition->all();

        foreach ($definition['questions'] as $questionKey => $question) {
            $answer = $answers[$questionKey] ?? null;

            if (! is_string($answer) || ! isset($question['scores'][$answer])) {
                continue;
            }

            foreach ($question['scores'][$answer] as $dimension => $value) {
                $totals[$dimension] = ($totals[$dimension] ?? 0) + $value;
                $counts[$dimension] = ($counts[$dimension] ?? 0) + 1;
            }
        }

        $maxDrawdown = (int) ($answers['q15_max_drawdown'] ?? 5);
        $willingnessValue = $definition['questions']['q15_max_drawdown']['willingness'][(string) $maxDrawdown] ?? 5;
        $totals['risk_willingness'] = ($totals['risk_willingness'] ?? 0) + $willingnessValue;
        $counts['risk_willingness'] = ($counts['risk_willingness'] ?? 0) + 1;

        $assetClassCount = count(array_unique((array) ($answers['q18_asset_classes'] ?? [])));
        $totals['investment_knowledge'] = ($totals['investment_knowledge'] ?? 0) + min(100, 10 + ($assetClassCount * 10));
        $counts['investment_knowledge'] = ($counts['investment_knowledge'] ?? 0) + 1;

        $liquidity = (array) ($answers['q10_liquidity'] ?? []);
        $liquidityNeed = $this->liquidityNeed($liquidity);
        $totals['liquidity_need'] = ($totals['liquidity_need'] ?? 0) + $liquidityNeed;
        $counts['liquidity_need'] = ($counts['liquidity_need'] ?? 0) + 1;
        $totals['risk_capacity'] = ($totals['risk_capacity'] ?? 0) + (100 - $liquidityNeed);
        $counts['risk_capacity'] = ($counts['risk_capacity'] ?? 0) + 1;

        $dimensions = [
            'risk_willingness',
            'risk_capacity',
            'financial_resilience',
            'liquidity_need',
            'investment_knowledge',
            'behavioral_stability',
            'loss_aversion',
            'return_ambition',
            'time_horizon',
        ];
        $scores = [];

        foreach ($dimensions as $dimension) {
            $scores[$dimension] = (int) round(($totals[$dimension] ?? 50) / max(1, $counts[$dimension] ?? 1));
        }

        $rawRisk = 0.0;
        foreach ($definition['weights'] as $dimension => $weight) {
            $rawRisk += $scores[$dimension] * $weight;
        }

        $capacityCeiling = 20 + ($scores['risk_capacity'] * 0.8);
        $willingnessCeiling = 20 + ($scores['risk_willingness'] * 0.8);
        $hardCaps = $this->hardCaps($answers, $scores, $maxDrawdown);
        $effectiveRisk = (int) round(min($rawRisk, $capacityCeiling, $willingnessCeiling, ...array_column($hardCaps, 'ceiling')));

        $scores['raw_risk'] = (int) round($rawRisk);
        $scores['capacity_ceiling'] = (int) round($capacityCeiling);
        $scores['willingness_ceiling'] = (int) round($willingnessCeiling);
        $scores['effective_risk'] = max(0, min(100, $effectiveRisk));

        return [
            'scores' => $scores,
            'risk_band' => $this->riskBand($scores['effective_risk']),
            'persona' => $this->personaResolver->resolve($scores),
            'maximum_tolerated_drawdown' => $maxDrawdown,
            'hard_caps' => $hardCaps,
            'warnings' => $this->warnings($answers, $scores),
        ];
    }

    /** @param array<string, mixed> $liquidity */
    private function liquidityNeed(array $liquidity): int
    {
        $proportion = ['almost_none' => 5, 'under_10' => 20, '10_25' => 45, '25_50' => 75, 'over_50' => 100];
        $speed = ['same_day' => 100, 'within_week' => 75, 'within_month' => 45, 'several_months' => 15];

        return (int) round((($proportion[$liquidity['proportion'] ?? 'over_50'] ?? 100) * 0.65)
            + (($speed[$liquidity['speed'] ?? 'same_day'] ?? 100) * 0.35));
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, int>  $scores
     * @return array<int, array{reason: string, ceiling: int}>
     */
    private function hardCaps(array $answers, array $scores, int $maxDrawdown): array
    {
        $caps = [['reason' => 'maximum_tolerated_drawdown', 'ceiling' => min(100, 10 + ($maxDrawdown * 2))]];

        if (($answers['q4_high_interest_debt'] ?? null) === 'significant') {
            $caps[] = ['reason' => 'significant_high_interest_debt', 'ceiling' => 25];
        }

        if (($answers['q3_emergency_fund'] ?? null) === 'under_1') {
            $caps[] = ['reason' => 'insufficient_emergency_savings', 'ceiling' => 25];
        }

        $horizonCaps = ['under_1_year' => 20, '1_3_years' => 35, '3_5_years' => 55];
        if (isset($horizonCaps[$answers['q8_time_horizon'] ?? ''])) {
            $caps[] = ['reason' => 'short_time_horizon', 'ceiling' => $horizonCaps[$answers['q8_time_horizon']]];
        }

        if (($answers['q7_goal_importance'] ?? null) === 'essential') {
            $caps[] = ['reason' => 'essential_goal', 'ceiling' => 35];
        }

        if (($answers['q5_portfolio_wealth_share'] ?? null) === 'over_75') {
            $caps[] = ['reason' => 'high_portfolio_dependency', 'ceiling' => 35];
        }

        if (($answers['q11_permanent_loss_impact'] ?? null) === 'severe') {
            $caps[] = ['reason' => 'severe_loss_consequence', 'ceiling' => 20];
        }

        if ($scores['behavioral_stability'] < 30) {
            $caps[] = ['reason' => 'low_behavioral_stability', 'ceiling' => 35];
        }

        return $caps;
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

    /** @param array<string, mixed> $answers
     * @param  array<string, int>  $scores
     * @return array<int, string>
     */
    private function warnings(array $answers, array $scores): array
    {
        $warnings = [];

        if (($answers['q22_target_return'] ?? null) === '18_plus' && $scores['effective_risk'] < 65) {
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
}
