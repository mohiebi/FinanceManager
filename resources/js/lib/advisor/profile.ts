import scoringDefinition from './scoring-v1.json' with { type: 'json' };

export type AdvisorAnswers = Record<string, unknown>;

export type AdvisorScores = {
    risk_willingness: number;
    risk_capacity: number;
    financial_resilience: number;
    liquidity_need: number;
    investment_knowledge: number;
    behavioral_stability: number;
    loss_aversion: number;
    return_ambition: number;
    time_horizon: number;
    raw_risk: number;
    capacity_ceiling: number;
    willingness_ceiling: number;
    effective_risk: number;
};

type ScoreResult = {
    scores: AdvisorScores;
    risk_band: keyof typeof scoringDefinition.risk_envelopes;
    persona: string;
    maximum_tolerated_drawdown: number;
    hard_caps: { reason: string; ceiling: number }[];
    warnings: string[];
};

const round = (value: number): number => Math.round(value);

export function scoreInvestorProfile(answers: AdvisorAnswers): ScoreResult {
    const totals: Record<string, number> = {};
    const counts: Record<string, number> = {};

    Object.entries(scoringDefinition.questions).forEach(
        ([questionKey, question]) => {
            const answer = answers[questionKey];
            const scoreMap = 'scores' in question ? question.scores : undefined;

            if (
                typeof answer !== 'string' ||
                !scoreMap ||
                !(answer in scoreMap)
            ) {
                return;
            }

            const dimensions = scoreMap[
                answer as keyof typeof scoreMap
            ] as Record<string, number>;
            Object.entries(dimensions).forEach(([dimension, value]) => {
                totals[dimension] = (totals[dimension] ?? 0) + value;
                counts[dimension] = (counts[dimension] ?? 0) + 1;
            });
        },
    );

    const maximumDrawdown = Number(answers.q15_max_drawdown ?? 5);
    const willingness = scoringDefinition.questions.q15_max_drawdown
        .willingness as Record<string, number>;
    totals.risk_willingness =
        (totals.risk_willingness ?? 0) +
        (willingness[String(maximumDrawdown)] ?? 5);
    counts.risk_willingness = (counts.risk_willingness ?? 0) + 1;

    const assetClassCount = new Set(
        Array.isArray(answers.q18_asset_classes)
            ? answers.q18_asset_classes
            : [],
    ).size;
    totals.investment_knowledge =
        (totals.investment_knowledge ?? 0) +
        Math.min(100, 10 + assetClassCount * 10);
    counts.investment_knowledge = (counts.investment_knowledge ?? 0) + 1;

    const liquidity = isRecord(answers.q10_liquidity)
        ? answers.q10_liquidity
        : {};
    const liquidityNeed = scoreLiquidity(liquidity);
    totals.liquidity_need = (totals.liquidity_need ?? 0) + liquidityNeed;
    counts.liquidity_need = (counts.liquidity_need ?? 0) + 1;
    totals.risk_capacity = (totals.risk_capacity ?? 0) + (100 - liquidityNeed);
    counts.risk_capacity = (counts.risk_capacity ?? 0) + 1;

    const dimensionNames = [
        'risk_willingness',
        'risk_capacity',
        'financial_resilience',
        'liquidity_need',
        'investment_knowledge',
        'behavioral_stability',
        'loss_aversion',
        'return_ambition',
        'time_horizon',
    ] as const;
    const scores = Object.fromEntries(
        dimensionNames.map((dimension) => [
            dimension,
            round(
                (totals[dimension] ?? 50) / Math.max(1, counts[dimension] ?? 1),
            ),
        ]),
    ) as unknown as AdvisorScores;
    const weights = scoringDefinition.weights as Record<string, number>;
    const rawRisk = Object.entries(weights).reduce(
        (total, [dimension, weight]) =>
            total + scores[dimension as keyof AdvisorScores] * weight,
        0,
    );
    const capacityCeiling = 20 + scores.risk_capacity * 0.8;
    const willingnessCeiling = 20 + scores.risk_willingness * 0.8;
    const hardCaps = buildHardCaps(answers, scores, maximumDrawdown);
    const effectiveRisk = round(
        Math.min(
            rawRisk,
            capacityCeiling,
            willingnessCeiling,
            ...hardCaps.map((cap) => cap.ceiling),
        ),
    );

    scores.raw_risk = round(rawRisk);
    scores.capacity_ceiling = round(capacityCeiling);
    scores.willingness_ceiling = round(willingnessCeiling);
    scores.effective_risk = Math.max(0, Math.min(100, effectiveRisk));

    const riskBand = riskBandFor(scores.effective_risk);

    return {
        scores,
        risk_band: riskBand,
        persona: personaFor(scores),
        maximum_tolerated_drawdown: maximumDrawdown,
        hard_caps: hardCaps,
        warnings: warningsFor(answers, scores),
    };
}

export function buildAdvisorProfile(
    answers: AdvisorAnswers,
): Record<string, unknown> {
    const result = scoreInvestorProfile(answers);
    const preferences = isRecord(answers.portfolio_preferences)
        ? answers.portfolio_preferences
        : {};
    const options = optionsCapability(
        isRecord(answers.options_capability) ? answers.options_capability : {},
    );
    const envelope = scoringDefinition.risk_envelopes[result.risk_band];
    const maximumSingleAsset = Number(
        preferences.maximum_single_asset_allocation ?? 50,
    );
    const selectedAssets = Array.isArray(preferences.assets)
        ? preferences.assets.filter(isRecord).map(safeAsset)
        : [];

    return {
        profile_version: 1,
        persona: result.persona,
        risk_band: result.risk_band,
        scores: result.scores,
        maximum_tolerated_drawdown: result.maximum_tolerated_drawdown,
        financial_context: {
            income_stability: answers.q2_income_stability,
            emergency_fund: answers.q3_emergency_fund,
            high_interest_debt: answers.q4_high_interest_debt,
            portfolio_share_of_liquid_wealth: answers.q5_portfolio_wealth_share,
        },
        loss_context: {
            permanent_loss_impact: answers.q11_permanent_loss_impact,
            drawdown_10_response: answers.q12_drawdown_10,
            drawdown_20_response: answers.q13_drawdown_20,
            drawdown_35_response: answers.q14_drawdown_35,
        },
        goals: {
            primary: answers.q6_primary_goal,
            importance: answers.q7_goal_importance,
            time_horizon: answers.q8_time_horizon,
            early_withdrawal_likelihood: answers.q9_early_withdrawal,
            liquidity: answers.q10_liquidity,
            target_return: answers.q22_target_return,
        },
        portfolio_preferences: {
            scope: preferences.scope,
            primary_currency: cleanText(
                preferences.primary_currency,
                12,
            ).toUpperCase(),
            country: cleanText(preferences.country, 80),
            markets: Array.isArray(preferences.markets)
                ? preferences.markets.map((market) => cleanText(market, 80))
                : [],
            tax_sensitive: Boolean(preferences.tax_sensitive),
        },
        selected_assets: selectedAssets,
        options_capability: options,
        constraints: {
            minimum_liquid_allocation: minimumLiquidAllocation(
                result.scores.liquidity_need,
            ),
            maximum_single_asset_allocation: Math.min(
                maximumSingleAsset,
                envelope.single_asset_max,
            ),
            maximum_high_risk_allocation: envelope.high_risk_max,
            maximum_speculative_allocation: envelope.speculative_max,
            maximum_options_risk_budget: options.maximum_risk_budget_percent,
            hard_caps: result.hard_caps,
        },
        warnings: result.warnings,
    };
}

function scoreLiquidity(liquidity: Record<string, unknown>): number {
    const proportion: Record<string, number> = {
        almost_none: 5,
        under_10: 20,
        '10_25': 45,
        '25_50': 75,
        over_50: 100,
    };
    const speed: Record<string, number> = {
        same_day: 100,
        within_week: 75,
        within_month: 45,
        several_months: 15,
    };

    return round(
        (proportion[String(liquidity.proportion ?? 'over_50')] ?? 100) * 0.65 +
            (speed[String(liquidity.speed ?? 'same_day')] ?? 100) * 0.35,
    );
}

function buildHardCaps(
    answers: AdvisorAnswers,
    scores: AdvisorScores,
    maximumDrawdown: number,
): { reason: string; ceiling: number }[] {
    const caps = [
        {
            reason: 'maximum_tolerated_drawdown',
            ceiling: Math.min(100, 10 + maximumDrawdown * 2),
        },
    ];

    if (answers.q4_high_interest_debt === 'significant') {
        caps.push({ reason: 'significant_high_interest_debt', ceiling: 25 });
    }

    if (answers.q3_emergency_fund === 'under_1') {
        caps.push({ reason: 'insufficient_emergency_savings', ceiling: 25 });
    }

    const horizonCaps: Record<string, number> = {
        under_1_year: 20,
        '1_3_years': 35,
        '3_5_years': 55,
    };
    const horizon = String(answers.q8_time_horizon ?? '');

    if (horizon in horizonCaps) {
        caps.push({
            reason: 'short_time_horizon',
            ceiling: horizonCaps[horizon],
        });
    }

    if (answers.q7_goal_importance === 'essential') {
        caps.push({ reason: 'essential_goal', ceiling: 35 });
    }

    if (answers.q5_portfolio_wealth_share === 'over_75') {
        caps.push({ reason: 'high_portfolio_dependency', ceiling: 35 });
    }

    if (answers.q11_permanent_loss_impact === 'severe') {
        caps.push({ reason: 'severe_loss_consequence', ceiling: 20 });
    }

    if (scores.behavioral_stability < 30) {
        caps.push({ reason: 'low_behavioral_stability', ceiling: 35 });
    }

    return caps;
}

function riskBandFor(
    score: number,
): keyof typeof scoringDefinition.risk_envelopes {
    if (score <= 20) {
        return 'very_conservative';
    }

    if (score <= 40) {
        return 'conservative';
    }

    if (score <= 60) {
        return 'balanced';
    }

    if (score <= 80) {
        return 'growth';
    }

    return 'aggressive';
}

function personaFor(scores: AdvisorScores): string {
    if (scores.effective_risk <= 20) {
        return 'capital_protector';
    }

    if (scores.effective_risk <= 40) {
        return 'cautious_builder';
    }

    if (scores.effective_risk <= 60) {
        return 'balanced_investor';
    }

    if (scores.effective_risk <= 80) {
        return 'strategic_growth_investor';
    }

    if (scores.behavioral_stability >= 70 && scores.risk_capacity >= 75) {
        return 'opportunistic_investor';
    }

    return 'aggressive_growth_investor';
}

function warningsFor(answers: AdvisorAnswers, scores: AdvisorScores): string[] {
    const warnings: string[] = [];

    if (answers.q22_target_return === '18_plus' && scores.effective_risk < 65) {
        warnings.push('return_expectation_exceeds_risk_capacity');
    }

    if (scores.risk_willingness > scores.risk_capacity + 20) {
        warnings.push('willingness_exceeds_capacity');
    }

    if (scores.risk_capacity > scores.risk_willingness + 20) {
        warnings.push('capacity_exceeds_willingness');
    }

    return warnings;
}

function optionsCapability(
    value: Record<string, unknown>,
): Record<string, unknown> {
    if ((value.willingness ?? 'no') === 'no') {
        return {
            willingness: 'no',
            knowledge_score: 0,
            experience_level: 'none',
            allowed_underlying_categories: [],
            allowed_strategy_families: [],
            maximum_risk_budget_percent: 0,
            monitoring_suitability: 'not_applicable',
        };
    }

    const experienceScores: Record<string, number> = {
        none: 0,
        under_1: 25,
        '1_3': 60,
        '3_plus': 90,
    };
    const tradeScores: Record<string, number> = {
        none: 0,
        '1_10': 25,
        '11_50': 65,
        '50_plus': 100,
    };
    const knowledgeAnswers = Array.isArray(value.knowledge_answers)
        ? value.knowledge_answers
        : [];
    const knowledgeScore = round(
        (experienceScores[String(value.experience_years)] ?? 0) * 0.35 +
            (tradeScores[String(value.trade_count)] ?? 0) * 0.35 +
            (knowledgeAnswers.filter(Boolean).length / 3) * 100 * 0.3,
    );
    const experienceLevel =
        knowledgeScore >= 70
            ? 'advanced'
            : knowledgeScore >= 40
              ? 'intermediate'
              : 'beginner';
    const strategies: string[] = [];
    const monitoringIsActive = ['daily', 'weekly'].includes(
        String(value.monitoring),
    );

    if (value.broker_access && knowledgeScore >= 20) {
        strategies.push('protective_put');
    }

    if (value.broker_access && value.cap_upside && knowledgeScore >= 30) {
        strategies.push('collar');
    }

    if (
        value.broker_access &&
        value.assignment_tolerance &&
        knowledgeScore >= 40
    ) {
        strategies.push('covered_call');
    }

    if (value.broker_access && knowledgeScore >= 50 && monitoringIsActive) {
        strategies.push('vertical_debit_spread');
    }

    if (value.broker_access && knowledgeScore >= 70 && monitoringIsActive) {
        strategies.push('defined_risk_vertical_credit_spread');
    }

    const riskCap =
        experienceLevel === 'advanced'
            ? 10
            : experienceLevel === 'intermediate'
              ? 5
              : 2;

    return {
        willingness: value.willingness,
        broker_access: Boolean(value.broker_access),
        knowledge_score: knowledgeScore,
        experience_level: experienceLevel,
        objective: value.objective ?? null,
        allowed_underlying_categories: Array.isArray(value.approved_underlyings)
            ? value.approved_underlyings
            : [],
        allowed_strategy_families: strategies,
        maximum_risk_budget_percent: value.broker_access
            ? Math.min(Number(value.maximum_risk_budget_percent ?? 0), riskCap)
            : 0,
        recurring_premium: Boolean(value.recurring_premium),
        cap_upside: Boolean(value.cap_upside),
        assignment_tolerance: Boolean(value.assignment_tolerance),
        monitoring_suitability: value.monitoring ?? 'rarely',
    };
}

function safeAsset(asset: Record<string, unknown>): Record<string, unknown> {
    return {
        asset_key: cleanText(asset.asset_key, 80),
        source: asset.source,
        investment_asset_id:
            asset.source === 'cashpilot'
                ? (asset.investment_asset_id ?? null)
                : null,
        name: cleanText(asset.name, 120),
        ticker: cleanText(asset.ticker, 30),
        identifier: cleanText(asset.identifier, 80),
        exchange_or_market: cleanText(asset.exchange_or_market, 80),
        country: cleanText(asset.country, 80),
        currency: cleanText(asset.currency, 12).toUpperCase(),
        category: asset.category,
        risk_band: asset.risk_band,
        liquidity: asset.liquidity,
        perspective: asset.perspective,
        conviction: asset.conviction,
        holding_period: asset.holding_period,
        inclusion: asset.inclusion,
    };
}

function minimumLiquidAllocation(liquidityNeed: number): number {
    if (liquidityNeed >= 80) {
        return 50;
    }

    if (liquidityNeed >= 60) {
        return 35;
    }

    if (liquidityNeed >= 40) {
        return 20;
    }

    if (liquidityNeed >= 20) {
        return 10;
    }

    return 5;
}

function cleanText(value: unknown, maximumLength: number): string {
    return String(value ?? '')
        .trim()
        .replace(/[\u0000-\u001F\u007F]/gu, '')
        .slice(0, maximumLength);
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
