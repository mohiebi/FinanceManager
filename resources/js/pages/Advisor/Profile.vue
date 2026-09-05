<script setup lang="ts">
import { Head, router, useHttp, usePage } from '@inertiajs/vue3';
import { LoaderCircle, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePageSubtitle } from '@/composables/usePageSubtitle';
import { documentNumber, sealDate } from '@/lib/advisor/format';
import { advisorGenerationErrorKey } from '@/lib/advisor/http-errors';
import { useAdvisorLabels } from '@/lib/advisor/labels';
import { dispatchMilesShortfall } from '@/lib/miles';
import { profile as advisorProfile } from '@/routes/advisor';
import {
    show as showRecommendation,
    store as storeRecommendation,
} from '@/routes/advisor/recommendations';
import type { RecommendationAccepted } from '@/types/advisor';

type ProfilePayload = {
    persona: string;
    risk_band: string;
    scores: Record<string, number>;
    maximum_tolerated_drawdown: number;
    constraints: Record<string, number | unknown[]>;
    selected_assets: {
        asset_key: string;
        name: string;
        category: string;
        risk_band: string;
        perspective: string;
        inclusion: string;
    }[];
    options_capability: {
        willingness: string;
        experience_level: string;
        knowledge_score: number;
        allowed_strategy_families: string[];
        maximum_risk_budget_percent: number;
    };
    warnings: string[];
};

const props = defineProps<{
    profile: {
        id: number;
        profile_version: number;
        scoring_version: number;
        payload: ProfilePayload;
        ai_enabled: boolean;
        completed_at: string | null;
    };
    recommendationPricing: {
        full_miles: number;
        guidance_miles: number;
        charging: boolean;
    };
}>();

const { t } = useI18n();
const { label } = useAdvisorLabels();
const page = usePage();
const generationError = ref('');
const generator = useHttp<Record<string, never>, RecommendationAccepted>({});
const milesBalance = computed(() => page.props.miles?.balance ?? 0);
const canGenerate = computed(
    () =>
        !props.recommendationPricing.charging ||
        milesBalance.value >= props.recommendationPricing.full_miles,
);

usePageSubtitle(() => t('advisor.profile.subtitle'));

const calendar = computed(
    () => (page.props.calendar as string | undefined) ?? 'gregorian',
);
const riskScore = computed(
    () => props.profile.payload.scores.effective_risk ?? 0,
);

/**
 * The eight deterministic dimensions, in the order the dossier prints them.
 *
 * The order is load-bearing: willingness and capacity sit together at the top
 * because the notice below the grid is usually about the gap between them.
 */
const scoreRows = computed(() =>
    (
        [
            'risk_willingness',
            'risk_capacity',
            'financial_resilience',
            'liquidity_need',
            'investment_knowledge',
            'behavioral_stability',
            'loss_aversion',
            'return_ambition',
        ] as const
    ).map((key) => {
        const value = props.profile.payload.scores[key] ?? 0;

        return {
            key,
            label: t(`advisor.profile.${key}`),
            value,
            // Gold marks a score at or above 70 — high enough that it is the
            // number shaping the plan rather than one of eight inputs.
            color: value >= 70 ? '#d9c48f' : '#02cd86',
        };
    }),
);

/** Hard caps only; `hard_caps` is a nested bag, not a row. */
const guardrails = computed(() =>
    Object.entries(props.profile.payload.constraints)
        .filter(([key]) => key !== 'hard_caps')
        .map(([key, value]) => ({
            key,
            label: label('constraints_labels', key),
            value: `${value}%`,
        })),
);

const optionsCapability = computed(
    () => props.profile.payload.options_capability,
);

const allowedStrategies = computed(() =>
    optionsCapability.value.allowed_strategy_families
        .map((strategy) => label('option_strategies', strategy))
        .join(' · '),
);

/**
 * Ask for a recommendation, then get out of the way.
 *
 * The provider call runs in a queued job, so all this waits for is the row that
 * represents it. The recommendation page takes it from there — which is what
 * makes closing the tab mid-generation survivable.
 */
async function generateRecommendation(): Promise<void> {
    generationError.value = '';

    try {
        const response = await generator.post(storeRecommendation().url);

        router.visit(showRecommendation(response.recommendation_id).url);
    } catch (error) {
        if (dispatchMilesShortfall(error)) {
            return;
        }

        generationError.value = t(advisorGenerationErrorKey(error));
    }
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Advisor', href: '/advisor' },
            { title: 'Advisor', href: advisorProfile().url },
        ],
    },
});
</script>

<template>
    <Head :title="t('advisor.profile.title')" />

    <div
        data-app-flush-bottom
        class="advisor-rise min-h-[calc(100svh-72px)] bg-background px-3.5 pt-3.5 pb-[120px] text-white lg:min-h-[calc(100svh-92px)] lg:px-7 lg:pt-[22px] lg:pb-[140px]"
    >
        <!-- The dossier. One bordered document rather than a page of cards:
             the point of this screen is that it reads as something issued. -->
        <section
            class="overflow-hidden rounded-[16px] border border-[#d9c48f]/22 bg-[linear-gradient(180deg,rgba(217,196,143,0.05),#1a1a1a_46%)]"
        >
            <div
                class="advisor-mono flex flex-wrap items-center justify-between gap-[18px] border-b border-[#d9c48f]/18 px-[26px] py-[15px] text-[10px] tracking-[0.16em] text-[#a08f68] uppercase"
            >
                <span class="advisor-figure"
                    >{{ t('advisor.profile.title') }} · No.
                    {{ documentNumber(props.profile.id) }} · v{{
                        props.profile.profile_version
                    }}</span
                >
                <span class="advisor-figure"
                    >{{ t('advisor.profile.sealed') }}
                    {{ sealDate(props.profile.completed_at, calendar) }} ·
                    {{ t('advisor.profile.scoring') }}
                    v{{ props.profile.scoring_version }}</span
                >
            </div>

            <div class="grid lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
                <div
                    class="relative border-b border-white/7 px-6 py-9 lg:border-e lg:border-b-0 lg:px-10 lg:pt-[42px] lg:pb-11"
                >
                    <div
                        class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_22%_18%,rgba(217,196,143,0.1),transparent_55%)]"
                        aria-hidden="true"
                    />
                    <div class="relative">
                        <p
                            class="advisor-mono text-[10px] tracking-[0.22em] text-[#686868] uppercase"
                        >
                            {{ t('advisor.profile.persona') }}
                        </p>
                        <h1
                            class="advisor-serif mt-3.5 text-[32px] leading-[1.08] md:text-[40px]"
                        >
                            {{
                                label('personas', props.profile.payload.persona)
                            }}
                        </h1>
                        <p
                            class="advisor-mono mt-3 text-[11px] tracking-[0.12em] text-[#d9c48f] uppercase"
                        >
                            {{
                                label(
                                    'risk_bands',
                                    props.profile.payload.risk_band,
                                )
                            }}
                        </p>

                        <div class="mt-11 flex items-end gap-3">
                            <span
                                class="advisor-mono advisor-figure text-[78px] leading-[0.84] font-medium tracking-[-0.05em] text-white"
                                >{{ riskScore }}</span
                            >
                            <span
                                class="advisor-mono pb-1 text-[11px] leading-[1.6] text-[#686868] uppercase"
                                ><span class="advisor-figure">/ 100</span
                                ><br />{{
                                    t('advisor.profile.risk_score')
                                }}</span
                            >
                        </div>
                        <!-- Green at the low end, gold at the high: the bar is
                             the one place the two accents meet, because the
                             score is exactly where capacity turns into ceremony. -->
                        <div
                            class="mt-5 h-[3px] overflow-hidden rounded-full bg-white/8"
                        >
                            <div
                                class="h-full rounded-full bg-[linear-gradient(90deg,#02cd86,#d9c48f)]"
                                :style="{ width: `${riskScore}%` }"
                            />
                        </div>

                        <div
                            class="mt-9 grid grid-cols-2 border-t border-white/8"
                        >
                            <div
                                class="border-e border-white/8 py-[18px] pe-[18px]"
                            >
                                <p
                                    class="advisor-mono text-[9.5px] tracking-[0.16em] text-[#686868] uppercase"
                                >
                                    {{ t('advisor.profile.max_drawdown') }}
                                </p>
                                <p
                                    class="advisor-mono advisor-figure mt-2 text-[26px] font-medium"
                                >
                                    {{
                                        props.profile.payload
                                            .maximum_tolerated_drawdown
                                    }}%
                                </p>
                            </div>
                            <div class="py-[18px] ps-[18px]">
                                <p
                                    class="advisor-mono text-[9.5px] tracking-[0.16em] text-[#686868] uppercase"
                                >
                                    {{ t('advisor.profile.assets_approved') }}
                                </p>
                                <p
                                    class="advisor-mono advisor-figure mt-2 text-[26px] font-medium"
                                >
                                    {{
                                        props.profile.payload.selected_assets
                                            .length
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-8 lg:px-[34px] lg:pt-[34px] lg:pb-9">
                    <div
                        class="flex flex-wrap items-baseline justify-between gap-3 pb-3"
                    >
                        <span
                            class="advisor-mono text-[10px] tracking-[0.2em] text-[#686868] uppercase"
                            >{{ t('advisor.profile.derived_scores') }}</span
                        >
                        <!-- Load-bearing. The separation between deterministic
                             scoring and AI generation is what is being paid for,
                             so it is stated on the document itself. -->
                        <span
                            class="advisor-mono text-[9.5px] tracking-[0.1em] text-[#5a5a5a] uppercase"
                            >{{ t('advisor.profile.deterministic') }}</span
                        >
                    </div>
                    <div class="advisor-rule grid gap-x-[34px] sm:grid-cols-2">
                        <div
                            v-for="row in scoreRows"
                            :key="row.key"
                            class="border-b border-white/7 pt-[13px] pb-3"
                        >
                            <div
                                class="flex items-baseline justify-between gap-2.5"
                            >
                                <span class="text-[12.5px] text-[#989898]">{{
                                    row.label
                                }}</span>
                                <span
                                    class="advisor-mono advisor-figure text-[12.5px]"
                                    >{{ row.value }}</span
                                >
                            </div>
                            <div class="mt-[9px] h-[2px] bg-white/7">
                                <div
                                    class="h-full"
                                    :style="{
                                        width: `${row.value}%`,
                                        backgroundColor: row.color,
                                    }"
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        v-for="warning in props.profile.payload.warnings"
                        :key="warning"
                        class="mt-[22px] flex gap-3 rounded-[12px] border border-[#d9c48f]/24 bg-[#d9c48f]/5 px-4 py-3.5"
                    >
                        <TriangleAlert
                            class="mt-0.5 size-[15px] shrink-0 text-[#d9c48f]"
                            :stroke-width="1.6"
                            aria-hidden="true"
                        />
                        <p class="text-[13px] leading-[1.65] text-[#cfc4a6]">
                            {{ label('profile_warnings', warning) }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <div class="mt-[18px] grid gap-[18px] lg:grid-cols-2">
            <section
                class="rounded-[16px] border border-white/7 bg-[#1a1a1a] px-[26px] pt-6 pb-2.5"
            >
                <div
                    class="flex flex-wrap items-baseline justify-between gap-3 pb-3.5"
                >
                    <span
                        class="advisor-mono text-[10px] tracking-[0.2em] text-[#686868] uppercase"
                        >{{ t('advisor.profile.constraints') }}</span
                    >
                    <span
                        class="advisor-mono text-[9.5px] text-[#5a5a5a] uppercase"
                        >{{ t('advisor.profile.hard_caps') }}</span
                    >
                </div>
                <div class="advisor-rule">
                    <div
                        v-for="guardrail in guardrails"
                        :key="guardrail.key"
                        class="flex items-baseline justify-between gap-3 border-b border-white/7 py-[13px]"
                    >
                        <span class="text-[13px] text-[#989898]">{{
                            guardrail.label
                        }}</span>
                        <span
                            class="advisor-mono advisor-figure text-[13px] text-white"
                            >{{ guardrail.value }}</span
                        >
                    </div>
                </div>
            </section>

            <section
                class="rounded-[16px] border border-white/7 bg-[#1a1a1a] px-[26px] py-6"
            >
                <p
                    class="advisor-mono pb-3.5 text-[10px] tracking-[0.2em] text-[#686868] uppercase"
                >
                    {{ t('advisor.profile.options') }}
                </p>
                <div class="advisor-rule grid grid-cols-2">
                    <div class="border-e border-b border-white/7 py-4 pe-4">
                        <p
                            class="advisor-mono text-[9.5px] tracking-[0.16em] text-[#686868] uppercase"
                        >
                            {{ t('advisor.profile.options_willingness') }}
                        </p>
                        <p class="mt-[7px] text-[15px]">
                            {{
                                label(
                                    'options_willingness',
                                    optionsCapability.willingness,
                                )
                            }}
                        </p>
                    </div>
                    <div class="border-b border-white/7 py-4 ps-4">
                        <p
                            class="advisor-mono text-[9.5px] tracking-[0.16em] text-[#686868] uppercase"
                        >
                            {{ t('advisor.profile.options_capability_level') }}
                        </p>
                        <p class="mt-[7px] text-[15px]">
                            {{
                                label(
                                    'options_experience',
                                    optionsCapability.experience_level,
                                )
                            }}
                        </p>
                    </div>
                    <div class="border-e border-white/7 pe-4 pt-4">
                        <p
                            class="advisor-mono text-[9.5px] tracking-[0.16em] text-[#686868] uppercase"
                        >
                            {{ t('advisor.profile.options_knowledge') }}
                        </p>
                        <p
                            class="advisor-mono advisor-figure mt-[7px] text-[15px]"
                        >
                            {{ optionsCapability.knowledge_score }} / 100
                        </p>
                    </div>
                    <div class="ps-4 pt-4">
                        <p
                            class="advisor-mono text-[9.5px] tracking-[0.16em] text-[#686868] uppercase"
                        >
                            {{ t('advisor.profile.options_risk_budget') }}
                        </p>
                        <p
                            class="advisor-mono advisor-figure mt-[7px] text-[15px]"
                        >
                            {{ optionsCapability.maximum_risk_budget_percent }}%
                        </p>
                    </div>
                </div>
                <p
                    v-if="allowedStrategies"
                    class="advisor-mono mt-5 text-[9.5px] tracking-[0.1em] text-[#5a5a5a] uppercase"
                >
                    {{ t('advisor.profile.allowed') }}: {{ allowedStrategies }}
                </p>
            </section>
        </div>

        <section
            class="mt-[18px] rounded-[16px] border border-white/7 bg-[#1a1a1a] px-[26px] pt-6 pb-1.5"
        >
            <div
                class="flex flex-wrap items-baseline justify-between gap-3 pb-3.5"
            >
                <span
                    class="advisor-mono text-[10px] tracking-[0.2em] text-[#686868] uppercase"
                    >{{ t('advisor.profile.assets') }}</span
                >
                <span class="advisor-mono text-[9.5px] text-[#5a5a5a] uppercase"
                    >◆ {{ t('advisor.portfolio.required_asset') }}</span
                >
            </div>
            <div class="advisor-rule overflow-x-auto">
                <div class="min-w-[560px]">
                    <div
                        class="advisor-mono grid grid-cols-[minmax(0,2fr)_minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)_30px] gap-4 border-b border-white/7 py-[11px] text-[9.5px] tracking-[0.14em] text-[#5a5a5a] uppercase"
                    >
                        <span>{{ t('advisor.recommendation.asset') }}</span>
                        <span>{{ t('advisor.profile.category') }}</span>
                        <span>{{ t('advisor.profile.risk') }}</span>
                        <span>{{ t('advisor.profile.outlook') }}</span>
                        <span />
                    </div>
                    <div
                        v-for="asset in props.profile.payload.selected_assets"
                        :key="asset.asset_key"
                        class="grid grid-cols-[minmax(0,2fr)_minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)_30px] items-center gap-4 border-b border-white/7 py-3.5"
                    >
                        <span class="truncate text-[13.5px] text-white">{{
                            asset.name
                        }}</span>
                        <span class="truncate text-[12.5px] text-[#989898]">{{
                            label('categories', asset.category)
                        }}</span>
                        <span class="truncate text-[12.5px] text-[#989898]">{{
                            label('risk_bands', asset.risk_band)
                        }}</span>
                        <span class="truncate text-[12.5px] text-[#989898]">{{
                            label('perspectives', asset.perspective)
                        }}</span>
                        <span class="text-end text-xs text-[#d9c48f]">
                            <template v-if="asset.inclusion === 'required'"
                                >◆</template
                            >
                            <span class="sr-only">{{
                                label('inclusions', asset.inclusion)
                            }}</span>
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <!-- The hand-off. Green, because this is the one action on the page. -->
        <section
            class="mt-[18px] flex flex-wrap items-center justify-between gap-[26px] rounded-[16px] border border-[#02cd86]/22 bg-[linear-gradient(120deg,rgba(2,205,134,0.07),#1a1a1a_52%)] px-7 py-[26px]"
        >
            <div class="max-w-[60ch] min-w-0">
                <h2
                    class="advisor-serif text-[22px] leading-[1.2] md:text-[24px]"
                >
                    {{ t('advisor.ai_role') }}
                </h2>
                <p class="mt-2.5 text-[13.5px] leading-[1.7] text-[#989898]">
                    {{ t('advisor.profile.ai_scope') }}
                </p>
                <p
                    class="advisor-mono mt-3 text-[10px] tracking-[0.1em] text-[#d9c48f] uppercase"
                >
                    {{
                        t('advisor.miles.recommendation_price', {
                            miles: props.recommendationPricing.full_miles,
                            guidance:
                                props.recommendationPricing.guidance_miles,
                        })
                    }}
                    <template v-if="!props.recommendationPricing.charging">
                        · {{ t('advisor.miles.shadow') }}
                    </template>
                </p>
            </div>
            <button
                type="button"
                class="flex shrink-0 cursor-pointer items-center gap-2.5 rounded-[10px] bg-[#02cd86] px-[26px] py-3.5 text-sm font-semibold text-[#101010] transition-colors hover:bg-[#16e19a] disabled:cursor-not-allowed disabled:opacity-45"
                :disabled="
                    !props.profile.ai_enabled ||
                    generator.processing ||
                    !canGenerate
                "
                @click="generateRecommendation"
            >
                {{
                    generator.processing
                        ? t('advisor.recommendation.starting')
                        : t('advisor.profile.generate')
                }}
                <LoaderCircle
                    v-if="generator.processing"
                    class="size-4 animate-spin motion-reduce:animate-none"
                    aria-hidden="true"
                />
                <span v-else class="advisor-mono text-[13px] rtl:rotate-180"
                    >→</span
                >
            </button>
        </section>

        <p
            v-if="!props.profile.ai_enabled"
            class="mt-4 text-[13px] leading-[1.65] text-[#cfc4a6]"
        >
            {{ t('advisor.profile.ai_disabled') }}
        </p>
        <p
            v-else-if="!canGenerate"
            class="mt-4 text-[13px] leading-[1.65] text-[#cfc4a6]"
        >
            {{
                t('advisor.miles.shortfall', {
                    miles:
                        props.recommendationPricing.full_miles - milesBalance,
                })
            }}
        </p>
        <p
            v-if="generationError"
            class="mt-4 rounded-[12px] border border-[#e9756f]/25 bg-[#e9756f]/8 px-4 py-3 text-sm text-[#f2b2ae]"
            role="alert"
        >
            {{ generationError }}
        </p>

        <p
            class="mx-auto mt-11 max-w-[68ch] text-center text-xs leading-[1.75] text-[#5a5a5a]"
        >
            {{ t('advisor.disclosure') }}
        </p>
    </div>
</template>
