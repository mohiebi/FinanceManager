<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    ChevronDown,
    LockKeyhole,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import { computed, reactive, ref, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { useVault } from '@/composables/useVault';
import { useAdvisorLabels } from '@/lib/advisor/labels';
import { buildAdvisorProfile } from '@/lib/advisor/profile';
import { complete } from '@/routes/advisor/assessments';
import { update as updateSection } from '@/routes/advisor/assessments/sections';
import type {
    OptionsCapabilityAnswer,
    PortfolioPreferences,
} from '@/types/advisor';
import type { Encrypted } from '@/types/vault';

type QuestionDefinition = {
    input: string;
    options?: string[];
    proportion_options?: string[];
    speed_options?: string[];
};
type AssessmentDefinition = {
    sections: { number: number; key: string; questions: string[] }[];
    questions: Record<string, QuestionDefinition>;
};
type SupportedAsset = {
    id: number;
    name: string;
    slug: string;
    unit: string;
    icon: string | null;
    color: string | null;
    category: string;
    risk_band: string;
    liquidity: string;
    currency: string;
};

const props = defineProps<{
    assessment: {
        id: number;
        status: string;
        assessment_version: number;
        scoring_version: number;
        last_completed_section: number;
    };
    currentSection: number;
    definition: AssessmentDefinition;
    answers: Record<string, Encrypted<unknown>>;
    supportedAssets: SupportedAsset[];
    vaultArmed: boolean;
}>();

const { t } = useI18n();
const { label } = useAdvisorLabels();
const { revealAsync, sealForSubmit, trackKey } = useVault();
const fullAnswers = reactive<Record<string, unknown>>({});
const hydrated = ref(false);
const processing = ref(false);
const clientError = ref('');
const aiConsent = ref(true);
/** Questions the last attempt found blank, marked in place rather than summarized. */
const missingKeys = ref<string[]>([]);

const section = computed(
    () =>
        props.definition.sections.find(
            (item) => item.number === props.currentSection,
        )!,
);
const questionKeys = computed(() => section.value.questions);
const portfolioPreferences = computed(
    () => fullAnswers.portfolio_preferences as PortfolioPreferences,
);
const optionsCapability = computed(
    () => fullAnswers.options_capability as OptionsCapabilityAnswer,
);
const marketsText = computed({
    get: () => portfolioPreferences.value?.markets.join(', ') ?? '',
    set: (value: string) => {
        portfolioPreferences.value.markets = value
            .split(',')
            .map((item) => item.trim())
            .filter(Boolean);
    },
});

watchEffect(async () => {
    trackKey();
    hydrated.value = false;

    for (const [key, value] of Object.entries(props.answers)) {
        const revealed = await revealAsync(
            value,
            'investor_assessment_answers',
            'json',
        );

        if (revealed !== undefined) {
            fullAnswers[key] = revealed;
        }
    }

    initializeSectionDefaults();
    hydrated.value =
        !props.vaultArmed ||
        Object.keys(fullAnswers).length === Object.keys(props.answers).length;
});

function initializeSectionDefaults(): void {
    if (props.currentSection === 2 && !isRecord(fullAnswers.q10_liquidity)) {
        fullAnswers.q10_liquidity = { proportion: '', speed: '' };
    }

    if (
        props.currentSection === 4 &&
        !Array.isArray(fullAnswers.q18_asset_classes)
    ) {
        fullAnswers.q18_asset_classes = [];
    }

    if (
        props.currentSection === 7 &&
        !isRecord(fullAnswers.portfolio_preferences)
    ) {
        fullAnswers.portfolio_preferences = {
            scope: 'both',
            new_investable_amount: null,
            recurring_contribution: null,
            primary_currency: 'USD',
            country: '',
            markets: [],
            maximum_single_asset_allocation: 50,
            tax_sensitive: false,
            assets: [],
        } satisfies PortfolioPreferences;
    }

    if (
        props.currentSection === 8 &&
        !isRecord(fullAnswers.options_capability)
    ) {
        fullAnswers.options_capability = {
            willingness: 'no',
            broker_access: false,
            approved_underlyings: [],
            experience_years: 'none',
            trade_count: 'none',
            strategies_used: [],
            knowledge_answers: [false, false, false],
            objective: 'downside_hedging',
            maximum_risk_budget_percent: 2,
            recurring_premium: false,
            cap_upside: false,
            assignment_tolerance: false,
            monitoring: 'weekly',
        } satisfies OptionsCapabilityAnswer;
    }
}

function selectSingle(key: string, value: string): void {
    fullAnswers[key] = value;
    clearMissing(key);
}

function toggleMulti(key: string, value: string): void {
    const values = Array.isArray(fullAnswers[key])
        ? [...(fullAnswers[key] as string[])]
        : [];
    fullAnswers[key] = values.includes(value)
        ? values.filter((item) => item !== value)
        : [...values, value];
    clearMissing(key);
}

/** Answering a marked question drops its mark straight away, not on next submit. */
function clearMissing(key: string): void {
    if (isBlank(fullAnswers[key])) {
        return;
    }

    missingKeys.value = missingKeys.value.filter((item) => item !== key);

    if (missingKeys.value.length === 0) {
        clientError.value = '';
    }
}

function selected(key: string, value: string): boolean {
    return Array.isArray(fullAnswers[key])
        ? (fullAnswers[key] as string[]).includes(value)
        : fullAnswers[key] === value;
}

function toggleList(values: string[], value: string): void {
    const index = values.indexOf(value);

    if (index >= 0) {
        values.splice(index, 1);
    } else {
        values.push(value);
    }
}

function toggleSupportedAsset(asset: SupportedAsset): void {
    const index = portfolioPreferences.value.assets.findIndex(
        (item) => item.asset_key === `cashpilot-${asset.id}`,
    );

    if (index >= 0) {
        portfolioPreferences.value.assets.splice(index, 1);

        return;
    }

    portfolioPreferences.value.assets.push({
        asset_key: `cashpilot-${asset.id}`,
        source: 'cashpilot',
        investment_asset_id: asset.id,
        name: asset.name,
        ticker: asset.slug.toUpperCase(),
        identifier: null,
        exchange_or_market: null,
        country: null,
        currency: asset.currency,
        category: asset.category,
        risk_band: asset.risk_band,
        liquidity: asset.liquidity,
        perspective: 'neutral',
        conviction: 'medium',
        holding_period: '5_10_years',
        inclusion: 'allowed',
        notes: null,
    });
}

function addCustomAsset(): void {
    const assetKey = `custom-${crypto.randomUUID()}`;
    // A blank custom asset has nothing to collapse — open it ready to fill in.
    expandedAssets.value = [...expandedAssets.value, assetKey];
    portfolioPreferences.value.assets.push({
        asset_key: assetKey,
        source: 'custom',
        investment_asset_id: null,
        name: '',
        ticker: null,
        identifier: null,
        exchange_or_market: null,
        country: portfolioPreferences.value.country || null,
        currency: portfolioPreferences.value.primary_currency,
        category: 'other',
        risk_band: 'unknown',
        liquidity: 'within_week',
        perspective: 'neutral',
        conviction: 'medium',
        holding_period: '5_10_years',
        inclusion: 'allowed',
        notes: null,
    });
}

function removeAsset(assetKey: string): void {
    portfolioPreferences.value.assets =
        portfolioPreferences.value.assets.filter(
            (item) => item.asset_key !== assetKey,
        );
}

/**
 * The option lists section 7 offers, named once.
 *
 * They were inlined in the template and rendered by swapping underscores for
 * spaces, which put English identifiers — `private_asset`, `5_10_years` — in
 * front of every reader regardless of locale. useAdvisorLabels translates them.
 */
const assetCategories = [
    'stock',
    'etf',
    'bond',
    'currency',
    'metal',
    'crypto',
    'commodity',
    'real_estate',
    'private_asset',
    'other',
] as const;
const assetRiskBands = [
    'defensive',
    'moderate',
    'growth',
    'speculative',
    'unknown',
] as const;
const assetLiquidities = [
    'same_day',
    'within_week',
    'within_month',
    'illiquid',
] as const;
const assetPerspectives = ['bearish', 'neutral', 'bullish'] as const;
const assetConvictions = ['low', 'medium', 'high'] as const;
const assetHoldingPeriods = [
    'under_1_year',
    '1_3_years',
    '3_5_years',
    '5_10_years',
    '10_plus',
] as const;

/**
 * Which asset cards are showing their full settings.
 *
 * Every card used to render eight unlabelled dropdowns at once — at the cap of
 * thirty assets, 240 of them on one page. The settings that matter for entry are
 * the name and the ticker; the rest have workable defaults and belong behind a
 * disclosure.
 */
const expandedAssets = ref<string[]>([]);

function toggleAssetDetails(assetKey: string): void {
    expandedAssets.value = expandedAssets.value.includes(assetKey)
        ? expandedAssets.value.filter((item) => item !== assetKey)
        : [...expandedAssets.value, assetKey];
}

/** The collapsed card still has to say what it is, in words. */
function assetSummary(asset: PortfolioPreferences['assets'][number]): string {
    return [
        label('categories', asset.category),
        label('risk_bands', asset.risk_band),
        label('perspectives', asset.perspective),
        label('holding_periods', asset.holding_period),
    ].join(' · ');
}

function isSupportedSelected(asset: SupportedAsset): boolean {
    return portfolioPreferences.value.assets.some(
        (item) => item.asset_key === `cashpilot-${asset.id}`,
    );
}

function isBlank(value: unknown): boolean {
    return (
        value === undefined ||
        value === null ||
        value === '' ||
        (Array.isArray(value) && value.length === 0)
    );
}

/**
 * Collect every unanswered question, not just the first.
 *
 * A single "Choose an answer to continue." under six questions makes the reader
 * re-check all of them. Naming which ones are missing — and how many — is the
 * whole difference between a hint and an instruction.
 */
function validateCurrentSection(): boolean {
    missingKeys.value = questionKeys.value.filter((key) =>
        isBlank(fullAnswers[key]),
    );

    if (props.currentSection === 2) {
        const liquidity = fullAnswers.q10_liquidity as Record<string, unknown>;

        if (
            (isBlank(liquidity?.proportion) || isBlank(liquidity?.speed)) &&
            !missingKeys.value.includes('q10_liquidity')
        ) {
            missingKeys.value = [...missingKeys.value, 'q10_liquidity'];
        }
    }

    if (missingKeys.value.length > 0) {
        clientError.value =
            missingKeys.value.length === 1
                ? t('advisor.assessment.missing_one')
                : t('advisor.assessment.missing_many', {
                      count: missingKeys.value.length,
                  });
        focusFirstMissing();

        return false;
    }

    if (props.currentSection === 7) {
        return validatePortfolioSection();
    }

    clientError.value = '';

    return true;
}

/**
 * Section 7 is one composite answer, so a per-question mark says nothing useful.
 * Name the field that is actually blocking instead.
 */
function validatePortfolioSection(): boolean {
    const preferences = portfolioPreferences.value;

    if (!preferences.country.trim()) {
        return failWith(t('advisor.assessment.missing_country'));
    }

    if (preferences.markets.length === 0) {
        return failWith(t('advisor.assessment.missing_markets'));
    }

    if (preferences.assets.length === 0) {
        return failWith(t('advisor.assessment.missing_assets'));
    }

    const unnamed = preferences.assets.find((asset) => !asset.name.trim());

    if (unnamed !== undefined) {
        return failWith(t('advisor.assessment.missing_asset_name'));
    }

    const unidentified = preferences.assets.find(
        (asset) =>
            asset.source === 'custom' && !asset.ticker && !asset.identifier,
    );

    if (unidentified !== undefined) {
        return failWith(
            t('advisor.assessment.missing_asset_ticker', {
                name: unidentified.name,
            }),
        );
    }

    clientError.value = '';

    return true;
}

function failWith(message: string): false {
    clientError.value = message;

    return false;
}

function focusFirstMissing(): void {
    const first = missingKeys.value[0];

    if (first === undefined) {
        return;
    }

    document
        .getElementById(`question-${first}`)
        ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

async function save(): Promise<void> {
    if (!validateCurrentSection() || processing.value) {
        return;
    }

    await submitSection(false);
}

/**
 * Keep what has been answered, then step back.
 *
 * Back used to navigate without saving, so checking something on the previous
 * page quietly cost you everything typed on this one. Validation is skipped —
 * a draft is allowed to be incomplete, that is the point.
 */
async function saveAndGoBack(): Promise<void> {
    if (props.currentSection <= 1 || processing.value) {
        return;
    }

    await submitSection(true);
}

async function submitSection(partial: boolean): Promise<void> {
    processing.value = true;
    clientError.value = '';

    try {
        const answers: Record<string, unknown> = {};

        for (const key of questionKeys.value) {
            const value = fullAnswers[key];

            // A draft carries only what exists; a completed section carries all
            // of it, and has already been checked by validateCurrentSection().
            if (partial && isBlank(value)) {
                continue;
            }

            if (props.vaultArmed) {
                const sealed = await sealForSubmit(
                    { answer: value },
                    'investor_assessment_answers',
                    { answer: 'json' },
                );
                answers[key] = sealed.answer;
            } else {
                answers[key] = value;
            }
        }

        router.patch(
            updateSection({
                assessment: props.assessment.id,
                section: props.currentSection,
            }).url,
            { answers, partial } as never,
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (!partial && props.currentSection === 8) {
                        finish();
                    }
                },
                onError: (errors) => {
                    clientError.value =
                        Object.values(errors)[0] ??
                        t('advisor.assessment.save_failed');
                },
                onFinish: () => {
                    if (partial || props.currentSection !== 8) {
                        processing.value = false;
                    }
                },
            },
        );
    } catch (error) {
        clientError.value =
            error instanceof Error
                ? error.message
                : t('advisor.assessment.save_failed');
        processing.value = false;
    }
}

function finish(): void {
    const derivedProfile = props.vaultArmed
        ? buildAdvisorProfile(fullAnswers)
        : null;
    router.post(
        complete(props.assessment.id).url,
        {
            ai_consent: aiConsent.value,
            derived_profile: derivedProfile,
        } as never,
        {
            onError: (errors) => {
                clientError.value =
                    Object.values(errors)[0] ??
                    t('advisor.assessment.save_failed');
            },
            onFinish: () => (processing.value = false),
        },
    );
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Advisor', href: '/advisor' },
            { title: 'Assessment', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="t('advisor.assessment.title')" />

    <div
        data-app-flush-bottom
        class="min-h-[calc(100svh-72px)] bg-background px-[18px] py-5 text-white lg:min-h-[calc(100svh-92px)]"
    >
        <header class="mx-auto max-w-5xl">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p
                        class="text-[11px] font-semibold tracking-[0.28em] text-[#02CD86] uppercase"
                    >
                        {{
                            t('advisor.assessment.step', {
                                current: props.currentSection,
                                total: 8,
                            })
                        }}
                    </p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight">
                        {{ t(`advisor.sections.${section.key}`) }}
                    </h1>
                </div>
                <div
                    class="hidden items-center gap-2 text-xs text-white/35 sm:flex"
                >
                    <LockKeyhole class="size-4 text-[#60a5fa]" />{{
                        t('advisor.assessment.saved')
                    }}
                </div>
            </div>
            <div
                class="mt-5 grid grid-cols-8 gap-1.5"
                aria-label="Assessment progress"
            >
                <div
                    v-for="step in 8"
                    :key="step"
                    class="h-1.5 rounded-full transition-colors"
                    :class="
                        step <= props.currentSection
                            ? 'bg-[#02CD86]'
                            : 'bg-white/10'
                    "
                />
            </div>
        </header>

        <main
            class="mx-auto mt-[18px] max-w-5xl rounded-[16px] border border-white/10 bg-[#171a19] p-5 shadow-[0_22px_65px_rgba(0,0,0,0.28)] md:p-8"
        >
            <div
                v-if="!hydrated"
                class="grid min-h-64 place-items-center text-sm text-white/40"
            >
                {{
                    props.vaultArmed
                        ? t('advisor.assessment.vault_notice')
                        : t('advisor.assessment.loading')
                }}
            </div>

            <div v-else-if="props.currentSection <= 6" class="space-y-8">
                <article
                    v-for="(key, questionIndex) in questionKeys"
                    :id="`question-${key}`"
                    :key="key"
                    class="scroll-mt-24 border-b border-white/8 pb-8 last:border-0 last:pb-0"
                >
                    <p
                        class="text-xs font-medium"
                        :class="
                            missingKeys.includes(key)
                                ? 'text-[#E94E50]'
                                : 'text-white/30'
                        "
                    >
                        {{ String(questionIndex + 1).padStart(2, '0') }}
                    </p>
                    <h2 class="mt-2 text-base leading-7 font-medium md:text-lg">
                        {{ t(`advisor.questions.${key}`) }}
                    </h2>
                    <!-- Marked where the question is, rather than summarized at
                         the foot of the page where it names nothing. -->
                    <p
                        v-if="missingKeys.includes(key)"
                        class="mt-2 text-xs text-[#E94E50]"
                    >
                        {{ t('advisor.assessment.needs_answer') }}
                    </p>

                    <div
                        v-if="definition.questions[key].input === 'liquidity'"
                        class="mt-4 grid gap-4 md:grid-cols-2"
                    >
                        <div>
                            <label
                                :for="`${key}-proportion`"
                                class="mb-2 block text-xs text-white/40"
                                >{{
                                    t('advisor.questions.liquidity_amount')
                                }}</label
                            >
                            <select
                                :id="`${key}-proportion`"
                                v-model="
                                    (
                                        fullAnswers.q10_liquidity as Record<
                                            string,
                                            string
                                        >
                                    ).proportion
                                "
                                class="advisor-input"
                            >
                                <option value="" disabled>
                                    {{
                                        t(
                                            'advisor.questions.liquidity_amount_placeholder',
                                        )
                                    }}
                                </option>
                                <option
                                    v-for="option in definition.questions[key]
                                        .proportion_options"
                                    :key="option"
                                    :value="option"
                                >
                                    {{ t(`advisor.options.${option}`) }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label
                                :for="`${key}-speed`"
                                class="mb-2 block text-xs text-white/40"
                                >{{
                                    t('advisor.questions.liquidity_speed')
                                }}</label
                            >
                            <select
                                :id="`${key}-speed`"
                                v-model="
                                    (
                                        fullAnswers.q10_liquidity as Record<
                                            string,
                                            string
                                        >
                                    ).speed
                                "
                                class="advisor-input"
                            >
                                <option value="" disabled>
                                    {{
                                        t(
                                            'advisor.questions.liquidity_speed_placeholder',
                                        )
                                    }}
                                </option>
                                <option
                                    v-for="option in definition.questions[key]
                                        .speed_options"
                                    :key="option"
                                    :value="option"
                                >
                                    {{ t(`advisor.options.${option}`) }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div v-else class="mt-4 grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="option in definition.questions[key].options"
                            :key="option"
                            type="button"
                            class="group flex min-h-12 cursor-pointer items-center gap-3 rounded-2xl border px-4 py-3 text-start text-sm transition focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:outline-none"
                            :class="
                                selected(key, option)
                                    ? 'border-[#02CD86]/50 bg-[#02CD86]/10 text-white'
                                    : 'border-white/10 bg-white/[0.025] text-white/60 hover:border-white/20 hover:text-white'
                            "
                            @click="
                                definition.questions[key].input === 'multi'
                                    ? toggleMulti(key, option)
                                    : selectSingle(key, option)
                            "
                        >
                            <span
                                class="grid size-5 shrink-0 place-items-center rounded-full border"
                                :class="
                                    selected(key, option)
                                        ? 'border-[#02CD86] bg-[#02CD86] text-[#07130f]'
                                        : 'border-white/20'
                                "
                            >
                                <Check
                                    v-if="selected(key, option)"
                                    class="size-3"
                                />
                            </span>
                            {{ t(`advisor.options.${option}`) }}
                        </button>
                    </div>
                    <p
                        v-if="key === 'q15_max_drawdown'"
                        class="mt-3 text-xs leading-5 text-white/35"
                    >
                        {{ t('advisor.questions.q15_max_drawdown_hint') }}
                    </p>
                </article>
            </div>

            <div v-else-if="props.currentSection === 7" class="space-y-8">
                <div class="grid gap-4 md:grid-cols-3">
                    <label
                        v-for="scope in ['current', 'new', 'both']"
                        :key="scope"
                        class="cursor-pointer rounded-2xl border p-4 transition"
                        :class="
                            portfolioPreferences.scope === scope
                                ? 'border-[#02CD86]/50 bg-[#02CD86]/10'
                                : 'border-white/10 bg-white/[0.025]'
                        "
                    >
                        <input
                            v-model="portfolioPreferences.scope"
                            class="sr-only"
                            type="radio"
                            :value="scope"
                        />
                        <span class="text-sm font-medium">{{
                            t(`advisor.portfolio.scope_${scope}`)
                        }}</span>
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <label class="advisor-field"
                        ><span>{{ t('advisor.portfolio.new_amount') }}</span
                        ><input
                            v-model.number="
                                portfolioPreferences.new_investable_amount
                            "
                            type="number"
                            min="0"
                            class="advisor-input"
                    /></label>
                    <label class="advisor-field"
                        ><span>{{ t('advisor.portfolio.recurring') }}</span
                        ><input
                            v-model.number="
                                portfolioPreferences.recurring_contribution
                            "
                            type="number"
                            min="0"
                            class="advisor-input"
                    /></label>
                    <label class="advisor-field"
                        ><span>{{ t('advisor.portfolio.currency') }}</span
                        ><input
                            v-model="portfolioPreferences.primary_currency"
                            maxlength="12"
                            class="advisor-input"
                    /></label>
                    <label class="advisor-field"
                        ><span>{{ t('advisor.portfolio.country') }}</span
                        ><input
                            v-model="portfolioPreferences.country"
                            maxlength="80"
                            class="advisor-input"
                    /></label>
                    <label class="advisor-field md:col-span-2"
                        ><span>{{ t('advisor.portfolio.markets') }}</span
                        ><input
                            v-model="marketsText"
                            maxlength="300"
                            class="advisor-input"
                            placeholder="NYSE, NASDAQ, LSE"
                    /></label>
                    <label class="advisor-field"
                        ><span
                            >{{ t('advisor.portfolio.max_concentration') }} ·
                            {{
                                portfolioPreferences.maximum_single_asset_allocation
                            }}%</span
                        ><input
                            v-model.number="
                                portfolioPreferences.maximum_single_asset_allocation
                            "
                            type="range"
                            min="5"
                            max="100"
                            step="5"
                            class="mt-3 w-full accent-[#02CD86]"
                    /></label>
                    <label
                        class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.025] p-4 text-sm text-white/65"
                        ><input
                            v-model="portfolioPreferences.tax_sensitive"
                            type="checkbox"
                            class="size-4 accent-[#02CD86]"
                        />{{ t('advisor.portfolio.tax_sensitive') }}</label
                    >
                </div>

                <div>
                    <h2 class="text-lg font-semibold">
                        {{ t('advisor.portfolio.assets') }}
                    </h2>
                    <p class="mt-1 text-sm text-white/40">
                        {{ t('advisor.portfolio.assets_help') }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-for="asset in props.supportedAssets"
                            :key="asset.id"
                            type="button"
                            class="cursor-pointer rounded-full border px-4 py-2 text-sm transition"
                            :class="
                                isSupportedSelected(asset)
                                    ? 'border-[#02CD86]/45 bg-[#02CD86]/10 text-[#8ff0cd]'
                                    : 'border-white/10 bg-white/[0.025] text-white/50 hover:text-white'
                            "
                            @click="toggleSupportedAsset(asset)"
                        >
                            <Check
                                v-if="isSupportedSelected(asset)"
                                class="me-1 inline size-3.5"
                            />{{ asset.name }}
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer rounded-full border border-dashed border-white/20 px-4 py-2 text-sm text-white/60 transition hover:border-[#02CD86]/40 hover:text-white"
                            @click="addCustomAsset"
                        >
                            <Plus class="me-1 inline size-3.5" />{{
                                t('advisor.portfolio.add_custom')
                            }}
                        </button>
                    </div>

                    <div class="mt-5 space-y-3">
                        <!-- Collapsed to a name and a plain-English summary. The
                             eight settings behind Details all have defaults that
                             work, so entry is name and ticker only. -->
                        <article
                            v-for="asset in portfolioPreferences.assets"
                            :key="asset.asset_key"
                            class="rounded-[20px] border border-white/10 bg-black/15 p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <input
                                        v-if="asset.source === 'custom'"
                                        v-model="asset.name"
                                        maxlength="120"
                                        class="advisor-input font-medium"
                                        :placeholder="
                                            t('advisor.portfolio.name')
                                        "
                                    />
                                    <h3 v-else class="font-medium">
                                        {{ asset.name }}
                                    </h3>
                                    <p class="mt-1.5 text-xs text-white/40">
                                        {{ assetSummary(asset) }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <button
                                        type="button"
                                        class="cursor-pointer rounded-full px-3 py-2 text-xs text-white/45 transition hover:bg-white/5 hover:text-white focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:outline-none"
                                        :aria-expanded="
                                            expandedAssets.includes(
                                                asset.asset_key,
                                            )
                                        "
                                        @click="
                                            toggleAssetDetails(asset.asset_key)
                                        "
                                    >
                                        {{ t('advisor.portfolio.details') }}
                                        <ChevronDown
                                            class="ms-1 inline size-3.5 transition-transform"
                                            :class="
                                                expandedAssets.includes(
                                                    asset.asset_key,
                                                )
                                                    ? 'rotate-180'
                                                    : ''
                                            "
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        class="grid size-9 cursor-pointer place-items-center rounded-full text-white/35 transition hover:bg-red-400/10 hover:text-red-300 focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:outline-none"
                                        :aria-label="
                                            t('advisor.portfolio.remove')
                                        "
                                        @click="removeAsset(asset.asset_key)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="expandedAssets.includes(asset.asset_key)"
                                class="mt-4 border-t border-white/8 pt-4"
                            >
                                <p class="text-xs leading-5 text-white/35">
                                    {{ t('advisor.portfolio.details_hint') }}
                                </p>
                                <div
                                    class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                                >
                                    <label
                                        v-if="asset.source === 'custom'"
                                        class="advisor-field"
                                        ><span>{{
                                            t('advisor.portfolio.ticker')
                                        }}</span
                                        ><input
                                            v-model="asset.ticker"
                                            maxlength="30"
                                            class="advisor-input"
                                    /></label>
                                    <label
                                        v-if="asset.source === 'custom'"
                                        class="advisor-field"
                                        ><span>{{
                                            t('advisor.portfolio.exchange')
                                        }}</span
                                        ><input
                                            v-model="asset.exchange_or_market"
                                            maxlength="80"
                                            class="advisor-input"
                                    /></label>
                                    <label class="advisor-field"
                                        ><span>{{
                                            t('advisor.portfolio.category')
                                        }}</span
                                        ><select
                                            v-model="asset.category"
                                            class="advisor-input"
                                            :disabled="
                                                asset.source === 'cashpilot'
                                            "
                                        >
                                            <option
                                                v-for="value in assetCategories"
                                                :key="value"
                                                :value="value"
                                            >
                                                {{ label('categories', value) }}
                                            </option>
                                        </select></label
                                    >
                                    <label class="advisor-field"
                                        ><span>{{
                                            t('advisor.portfolio.risk_band')
                                        }}</span
                                        ><select
                                            v-model="asset.risk_band"
                                            class="advisor-input"
                                            :disabled="
                                                asset.source === 'cashpilot'
                                            "
                                        >
                                            <option
                                                v-for="value in assetRiskBands"
                                                :key="value"
                                                :value="value"
                                            >
                                                {{ label('risk_bands', value) }}
                                            </option>
                                        </select></label
                                    >
                                    <label class="advisor-field"
                                        ><span>{{
                                            t('advisor.portfolio.liquidity')
                                        }}</span
                                        ><select
                                            v-model="asset.liquidity"
                                            class="advisor-input"
                                            :disabled="
                                                asset.source === 'cashpilot'
                                            "
                                        >
                                            <option
                                                v-for="value in assetLiquidities"
                                                :key="value"
                                                :value="value"
                                            >
                                                {{
                                                    label('liquidities', value)
                                                }}
                                            </option>
                                        </select></label
                                    >
                                    <label class="advisor-field"
                                        ><span>{{
                                            t('advisor.portfolio.perspective')
                                        }}</span
                                        ><select
                                            v-model="asset.perspective"
                                            class="advisor-input"
                                        >
                                            <option
                                                v-for="value in assetPerspectives"
                                                :key="value"
                                                :value="value"
                                            >
                                                {{
                                                    label('perspectives', value)
                                                }}
                                            </option>
                                        </select></label
                                    >
                                    <label class="advisor-field"
                                        ><span>{{
                                            t('advisor.portfolio.conviction')
                                        }}</span
                                        ><select
                                            v-model="asset.conviction"
                                            class="advisor-input"
                                        >
                                            <option
                                                v-for="value in assetConvictions"
                                                :key="value"
                                                :value="value"
                                            >
                                                {{
                                                    label('convictions', value)
                                                }}
                                            </option>
                                        </select></label
                                    >
                                    <label class="advisor-field"
                                        ><span>{{
                                            t(
                                                'advisor.portfolio.holding_period',
                                            )
                                        }}</span
                                        ><select
                                            v-model="asset.holding_period"
                                            class="advisor-input"
                                        >
                                            <option
                                                v-for="value in assetHoldingPeriods"
                                                :key="value"
                                                :value="value"
                                            >
                                                {{
                                                    label(
                                                        'holding_periods',
                                                        value,
                                                    )
                                                }}
                                            </option>
                                        </select></label
                                    >
                                    <label class="advisor-field"
                                        ><span>{{
                                            t('advisor.portfolio.inclusion')
                                        }}</span
                                        ><select
                                            v-model="asset.inclusion"
                                            class="advisor-input"
                                        >
                                            <option value="allowed">
                                                {{
                                                    t(
                                                        'advisor.portfolio.allowed_asset',
                                                    )
                                                }}
                                            </option>
                                            <option value="required">
                                                {{
                                                    t(
                                                        'advisor.portfolio.required_asset',
                                                    )
                                                }}
                                            </option>
                                        </select></label
                                    >
                                </div>
                                <label
                                    v-if="asset.source === 'custom'"
                                    class="advisor-field mt-3 block"
                                    ><span>{{
                                        t('advisor.portfolio.notes')
                                    }}</span
                                    ><textarea
                                        v-model="asset.notes"
                                        maxlength="300"
                                        rows="2"
                                        class="advisor-input resize-none"
                                    />
                                </label>
                            </div>
                        </article>
                    </div>
                </div>
            </div>

            <div v-else class="space-y-8">
                <div>
                    <h2 class="text-lg font-semibold">
                        {{ t('advisor.options_section.willingness') }}
                    </h2>
                    <div class="mt-4 grid gap-2 md:grid-cols-3">
                        <button
                            v-for="value in ['no', 'yes', 'not_sure']"
                            :key="value"
                            type="button"
                            class="cursor-pointer rounded-2xl border p-4 text-start text-sm transition"
                            :class="
                                optionsCapability.willingness === value
                                    ? 'border-[#02CD86]/50 bg-[#02CD86]/10 text-white'
                                    : 'border-white/10 bg-white/[0.025] text-white/55'
                            "
                            @click="
                                optionsCapability.willingness =
                                    value as OptionsCapabilityAnswer['willingness']
                            "
                        >
                            {{ t(`advisor.options_section.${value}`) }}
                        </button>
                    </div>
                </div>

                <template v-if="optionsCapability.willingness !== 'no'">
                    <label
                        class="flex items-center gap-3 rounded-2xl border border-white/10 p-4 text-sm text-white/65"
                        ><input
                            v-model="optionsCapability.broker_access"
                            type="checkbox"
                            class="size-4 accent-[#02CD86]"
                        />{{
                            t('advisor.options_section.broker_access')
                        }}</label
                    >
                    <div>
                        <p class="text-sm font-medium">
                            {{ t('advisor.options_section.underlyings') }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                v-for="value in [
                                    'stocks',
                                    'etfs',
                                    'indices',
                                    'commodities',
                                    'currencies',
                                    'crypto',
                                ]"
                                :key="value"
                                type="button"
                                class="cursor-pointer rounded-full border px-3 py-2 text-xs"
                                :class="
                                    optionsCapability.approved_underlyings.includes(
                                        value,
                                    )
                                        ? 'border-[#02CD86]/45 bg-[#02CD86]/10 text-[#8ff0cd]'
                                        : 'border-white/10 text-white/45'
                                "
                                @click="
                                    toggleList(
                                        optionsCapability.approved_underlyings,
                                        value,
                                    )
                                "
                            >
                                {{ label('underlyings', value) }}
                            </button>
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <label class="advisor-field"
                            ><span>{{
                                t('advisor.options_section.experience')
                            }}</span
                            ><select
                                v-model="optionsCapability.experience_years"
                                class="advisor-input"
                            >
                                <option
                                    v-for="value in [
                                        'none',
                                        'under_1',
                                        '1_3',
                                        '3_plus',
                                    ]"
                                    :key="value"
                                    :value="value"
                                >
                                    {{
                                        label('options_experience_years', value)
                                    }}
                                </option>
                            </select></label
                        >
                        <label class="advisor-field"
                            ><span>{{
                                t('advisor.options_section.trades')
                            }}</span
                            ><select
                                v-model="optionsCapability.trade_count"
                                class="advisor-input"
                            >
                                <option
                                    v-for="value in [
                                        'none',
                                        '1_10',
                                        '11_50',
                                        '50_plus',
                                    ]"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label('options_trade_counts', value) }}
                                </option>
                            </select></label
                        >
                        <label class="advisor-field"
                            ><span>{{
                                t('advisor.options_section.objective')
                            }}</span
                            ><select
                                v-model="optionsCapability.objective"
                                class="advisor-input"
                            >
                                <option
                                    v-for="value in [
                                        'downside_hedging',
                                        'income',
                                        'defined_risk_growth',
                                        'combination',
                                    ]"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label('options_objectives', value) }}
                                </option>
                            </select></label
                        >
                        <label class="advisor-field"
                            ><span>{{
                                t('advisor.options_section.monitoring')
                            }}</span
                            ><select
                                v-model="optionsCapability.monitoring"
                                class="advisor-input"
                            >
                                <option
                                    v-for="value in [
                                        'daily',
                                        'weekly',
                                        'monthly',
                                        'rarely',
                                    ]"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label('options_monitoring', value) }}
                                </option>
                            </select></label
                        >
                    </div>
                    <div class="space-y-2">
                        <p class="text-sm font-medium">
                            {{ t('advisor.options_section.knowledge') }}
                        </p>
                        <label
                            v-for="(label, index) in [
                                t('advisor.options_section.defined_loss'),
                                t('advisor.options_section.covered'),
                                t('advisor.options_section.expiry'),
                            ]"
                            :key="index"
                            class="flex items-center gap-3 rounded-xl border border-white/10 p-3 text-sm text-white/60"
                            ><input
                                v-model="
                                    optionsCapability.knowledge_answers[index]
                                "
                                type="checkbox"
                                class="size-4 accent-[#02CD86]"
                            />{{ label }}</label
                        >
                    </div>
                    <label class="advisor-field"
                        ><span
                            >{{ t('advisor.options_section.max_budget') }} ·
                            {{
                                optionsCapability.maximum_risk_budget_percent
                            }}%</span
                        ><input
                            v-model.number="
                                optionsCapability.maximum_risk_budget_percent
                            "
                            type="range"
                            min="0"
                            max="10"
                            step="0.5"
                            class="mt-3 w-full accent-[#02CD86]"
                    /></label>
                    <div class="grid gap-3 md:grid-cols-3">
                        <label
                            v-for="field in [
                                'recurring_premium',
                                'cap_upside',
                                'assignment_tolerance',
                            ] as const"
                            :key="field"
                            class="flex items-center gap-3 rounded-xl border border-white/10 p-3 text-sm text-white/60"
                            ><input
                                v-model="optionsCapability[field]"
                                type="checkbox"
                                class="size-4 accent-[#02CD86]"
                            />{{
                                t(
                                    `advisor.options_section.${field === 'assignment_tolerance' ? 'assignment' : field}`,
                                )
                            }}</label
                        >
                    </div>
                </template>

                <div
                    class="rounded-[20px] border border-[#60a5fa]/20 bg-[#60a5fa]/7 p-5"
                >
                    <h2 class="flex items-center gap-2 font-semibold">
                        <LockKeyhole class="size-4 text-[#60a5fa]" />{{
                            t('advisor.consent.title')
                        }}
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-white/55">
                        {{ t('advisor.consent.body') }}
                    </p>
                    <label
                        class="mt-4 flex cursor-pointer items-start gap-3 text-sm"
                        ><input
                            v-model="aiConsent"
                            type="checkbox"
                            class="mt-0.5 size-4 accent-[#02CD86]"
                        /><span
                            >{{ t('advisor.consent.agree')
                            }}<small class="mt-1 block text-white/35">{{
                                t('advisor.consent.decline_note')
                            }}</small></span
                        ></label
                    >
                </div>
            </div>

            <p
                v-if="clientError"
                class="mt-6 rounded-xl bg-red-400/10 px-4 py-3 text-sm text-red-200"
                role="alert"
            >
                {{ clientError }}
            </p>

            <footer
                class="mt-8 flex items-center justify-between gap-3 border-t border-white/8 pt-5"
            >
                <Button
                    variant="outline"
                    class="rounded-full border-white/10 bg-transparent text-white hover:bg-white/5"
                    :disabled="props.currentSection === 1 || processing"
                    @click="saveAndGoBack"
                    ><ArrowLeft class="size-4 rtl:rotate-180" />{{
                        t('advisor.assessment.back')
                    }}</Button
                >
                <Button
                    class="rounded-full bg-[#02CD86] px-6 font-semibold text-[#07130f] hover:bg-[#19d897]"
                    :disabled="processing || !hydrated"
                    @click="save"
                    >{{
                        props.currentSection === 8
                            ? t('advisor.assessment.finish')
                            : t('advisor.assessment.continue')
                    }}<ArrowRight class="size-4 rtl:rotate-180"
                /></Button>
            </footer>
        </main>
    </div>
</template>

<style scoped>
@reference '../../../css/app.css';

.advisor-input {
    @apply h-11 w-full rounded-xl border border-white/10 bg-[#222625] px-3 text-sm text-white transition outline-none placeholder:text-white/25 focus:border-[#02CD86]/60 focus:ring-2 focus:ring-[#02CD86]/10 disabled:cursor-not-allowed disabled:opacity-55;
}
textarea.advisor-input {
    @apply h-auto py-3;
}
.advisor-field {
    @apply block text-xs text-white/45;
}
.advisor-field > .advisor-input {
    @apply mt-2;
}
</style>
