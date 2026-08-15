import type { Encrypted } from '@/types/vault';

export type AdvisorAsset = {
    asset_key: string;
    source: 'cashpilot' | 'custom';
    investment_asset_id: number | null;
    name: string;
    ticker: string | null;
    identifier: string | null;
    exchange_or_market: string | null;
    country: string | null;
    currency: string;
    category: string;
    risk_band: string;
    liquidity: string;
    perspective: string;
    conviction: string;
    holding_period: string;
    inclusion: string;
    notes: string | null;
};

export type PortfolioPreferences = {
    scope: 'current' | 'new' | 'both';
    new_investable_amount: number | null;
    recurring_contribution: number | null;
    primary_currency: string;
    country: string;
    markets: string[];
    maximum_single_asset_allocation: number;
    tax_sensitive: boolean;
    assets: AdvisorAsset[];
};

export type OptionsCapabilityAnswer = {
    willingness: 'no' | 'yes' | 'not_sure';
    broker_access: boolean;
    approved_underlyings: string[];
    experience_years: string;
    trade_count: string;
    strategies_used: string[];
    knowledge_answers: boolean[];
    objective: string;
    maximum_risk_budget_percent: number;
    recurring_premium: boolean;
    cap_upside: boolean;
    assignment_tolerance: boolean;
    monitoring: string;
};

export type Allocation = {
    asset_key: string;
    target_percent: number;
    role: string;
    rationale: string;
};

export type OptionsOverlay = {
    strategy: string;
    underlying_asset_keys: string[];
    purpose: string;
    coverage_percent: number;
    maximum_risk_budget_percent: number;
    conditions: string[];
    benefits: string[];
    tradeoffs: string[];
};

export type PortfolioPlan = {
    name: string;
    allocations: Allocation[];
    options_overlays: OptionsOverlay[];
    risks: string[];
    tradeoffs: string[];
    what_would_change_this_plan: string[];
};

export type ClarificationQuestion = {
    key: string;
    question: string;
    reason: string;
    input_type: 'single_choice' | 'text' | 'boolean';
    options: string[];
};

export type AdvisorRecommendationPayload = {
    status:
        | 'needs_clarification'
        | 'recommendation_ready'
        | 'guidance_only'
        | 'cannot_recommend';
    questions: ClarificationQuestion[];
    suggested_additional_assets: {
        key: string;
        name: string;
        category: string;
        reason: string;
    }[];
    summary: string | null;
    primary: PortfolioPlan | null;
    safer_alternative: PortfolioPlan | null;
    higher_risk_alternative:
        | (PortfolioPlan & {
              available: boolean;
              reason_if_unavailable: string | null;
          })
        | null;
    uncertainties: string[];
    knowledge_limitations: string[];
    cannot_recommend_reason: string | null;
    fit_status?: 'fits' | 'closest_fit' | 'guidance_only';
    fit_warning?: string | null;
    next_steps?: string[];
    response_warnings?: string[];
    transition_plan?: {
        base_currency: string;
        combined_capital: number | null;
        exact_amounts_available: boolean;
        rows: {
            asset_key: string;
            current_percent: number | null;
            target_percent: number;
            percentage_point_difference: number | null;
            current_value: number | null;
            target_value: number | null;
            difference: number | null;
            price_available: boolean;
        }[];
        unselected_holdings: {
            name: string;
            current_value: number | null;
            target_percent: number;
            difference: number | null;
            price_available: boolean;
        }[];
    } | null;
};

export type RecommendationResponse = {
    recommendation_id: string;
    status: string;
    payload?: AdvisorRecommendationPayload;
    output_hash?: string;
    failure_code?: string;
    vault_seal_required: boolean;
};

/**
 * What asking for a recommendation returns now that a job does the work: an
 * identity to watch, not a result. The outcome arrives through the page's own
 * polling.
 */
export type RecommendationAccepted = {
    recommendation_id: string;
    status: string;
};

export type RecommendationProp = {
    id: string;
    status: string;
    mode: string;
    payload: Encrypted<AdvisorRecommendationPayload> | null;
    output_hash: string | null;
    failure_code: string | null;
    generated_at: string | null;
    /** Both move before the work they describe, so together they read as a stage. */
    provider_calls: number;
    repair_attempts: number;
    created_at: string;
};
