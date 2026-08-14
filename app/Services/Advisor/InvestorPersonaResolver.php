<?php

namespace App\Services\Advisor;

class InvestorPersonaResolver
{
    /** @param array<string, int|float> $scores */
    public function resolve(array $scores): string
    {
        $effectiveRisk = (int) $scores['effective_risk'];

        if ($effectiveRisk <= 20) {
            return 'capital_protector';
        }

        if ($effectiveRisk <= 40) {
            return 'cautious_builder';
        }

        if ($effectiveRisk <= 60) {
            return 'balanced_investor';
        }

        if ($effectiveRisk <= 80) {
            return 'strategic_growth_investor';
        }

        if (($scores['behavioral_stability'] ?? 0) >= 70 && ($scores['risk_capacity'] ?? 0) >= 75) {
            return 'opportunistic_investor';
        }

        return 'aggressive_growth_investor';
    }
}
