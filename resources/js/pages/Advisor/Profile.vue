<script setup lang="ts">
import { Head, router, useHttp } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    BrainCircuit,
    CheckCircle2,
    LockKeyhole,
    ShieldCheck,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { useVault } from '@/composables/useVault';
import { profile as advisorProfile } from '@/routes/advisor';
import {
    seal as sealRecommendation,
    show as showRecommendation,
    store as storeRecommendation,
} from '@/routes/advisor/recommendations';
import type {
    AdvisorRecommendationPayload,
    RecommendationResponse,
} from '@/types/advisor';

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
        payload: ProfilePayload;
        ai_enabled: boolean;
        completed_at: string | null;
    };
}>();

const { t } = useI18n();
const { sealForSubmit } = useVault();
const generationError = ref('');
const generator = useHttp<Record<string, never>, RecommendationResponse>({});
const sealer = useHttp<
    {
        recommendation_payload: AdvisorRecommendationPayload | string | null;
        output_hash: string;
    },
    { status: string }
>({ recommendation_payload: null, output_hash: '' });
const riskScore = computed(
    () => props.profile.payload.scores.effective_risk ?? 0,
);
const scoreRows = computed(
    () =>
        [
            ['risk_willingness', 'risk_willingness'],
            ['risk_capacity', 'risk_capacity'],
            ['financial_resilience', 'financial_resilience'],
            ['liquidity_need', 'liquidity_need'],
            ['investment_knowledge', 'investment_knowledge'],
            ['behavioral_stability', 'behavioral_stability'],
            ['loss_aversion', 'loss_aversion'],
            ['return_ambition', 'return_ambition'],
        ] as const,
);

async function generateRecommendation(): Promise<void> {
    generationError.value = '';

    try {
        const response = await generator.post(storeRecommendation().url);

        if (response.status === 'failed' || !response.payload) {
            generationError.value = t('advisor.recommendation.failed');

            return;
        }

        if (response.vault_seal_required) {
            const sealed = await sealForSubmit(
                { recommendation_payload: response.payload },
                'advisor_recommendations',
                { recommendation_payload: 'json' },
            );
            sealer.recommendation_payload =
                sealed.recommendation_payload as unknown as string;
            sealer.output_hash = response.output_hash ?? '';
            await sealer.patch(
                sealRecommendation(response.recommendation_id).url,
            );
        }

        router.visit(showRecommendation(response.recommendation_id).url);
    } catch (error) {
        generationError.value =
            error instanceof Error
                ? error.message
                : t('advisor.recommendation.failed');
    }
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Advisor', href: '/advisor' },
            { title: 'Profile', href: advisorProfile().url },
        ],
    },
});
</script>

<template>
    <Head :title="t('advisor.profile.title')" />

    <div
        class="min-h-[calc(100vh-92px)] bg-[#0d0f0f] px-[18px] py-5 text-white"
    >
        <section
            class="mx-auto max-w-6xl overflow-hidden rounded-[28px] border border-white/10 bg-[#171a19] shadow-[0_24px_70px_rgba(0,0,0,0.3)]"
        >
            <div class="grid lg:grid-cols-[0.8fr_1.2fr]">
                <div
                    class="relative border-b border-white/8 p-7 lg:border-e lg:border-b-0 lg:p-10"
                >
                    <div
                        class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(2,205,134,0.11),transparent_42%)]"
                    />
                    <div class="relative">
                        <div
                            class="flex items-center gap-2 text-xs font-semibold tracking-[0.25em] text-[#02CD86] uppercase"
                        >
                            <BrainCircuit class="size-4" />{{
                                t('advisor.profile.title')
                            }}
                        </div>
                        <h1
                            class="mt-5 text-3xl font-semibold tracking-[-0.03em] md:text-4xl"
                        >
                            {{
                                props.profile.payload.persona.replaceAll(
                                    '_',
                                    ' ',
                                )
                            }}
                        </h1>
                        <p class="mt-2 text-sm text-white/40 capitalize">
                            {{
                                props.profile.payload.risk_band.replaceAll(
                                    '_',
                                    ' ',
                                )
                            }}
                        </p>

                        <div class="mt-8 flex items-end gap-3">
                            <span
                                class="text-6xl font-semibold tracking-[-0.06em] text-[#02CD86]"
                                >{{ riskScore }}</span
                            >
                            <span class="mb-2 text-sm text-white/35"
                                >/ 100<br />{{
                                    t('advisor.profile.risk_score')
                                }}</span
                            >
                        </div>
                        <div
                            class="mt-4 h-2 overflow-hidden rounded-full bg-white/8"
                        >
                            <div
                                class="h-full rounded-full bg-[linear-gradient(90deg,#02CD86,#eab308,#f97316)]"
                                :style="{ width: `${riskScore}%` }"
                            />
                        </div>

                        <div
                            class="mt-8 rounded-2xl border border-white/8 bg-black/15 p-4"
                        >
                            <p class="text-xs text-white/35">
                                {{ t('advisor.profile.max_drawdown') }}
                            </p>
                            <p class="mt-1 text-xl font-semibold">
                                {{
                                    props.profile.payload
                                        .maximum_tolerated_drawdown
                                }}%
                            </p>
                        </div>
                    </div>
                </div>

                <div class="p-6 md:p-8">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div
                            v-for="[key, label] in scoreRows"
                            :key="key"
                            class="rounded-2xl border border-white/8 bg-white/[0.025] p-4"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="text-xs text-white/42">{{
                                    t(`advisor.profile.${label}`)
                                }}</span
                                ><strong class="text-sm">{{
                                    props.profile.payload.scores[key]
                                }}</strong>
                            </div>
                            <div class="mt-3 h-1 rounded-full bg-white/8">
                                <div
                                    class="h-full rounded-full bg-[#02CD86]/75"
                                    :style="{
                                        width: `${props.profile.payload.scores[key]}%`,
                                    }"
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="props.profile.payload.warnings.length"
                        class="mt-5 space-y-2"
                    >
                        <div
                            v-for="warning in props.profile.payload.warnings"
                            :key="warning"
                            class="flex gap-3 rounded-xl border border-amber-400/15 bg-amber-400/7 p-3 text-sm text-amber-100/80"
                        >
                            <AlertTriangle class="mt-0.5 size-4 shrink-0" />{{
                                warning.replaceAll('_', ' ')
                            }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="mx-auto mt-[18px] grid max-w-6xl gap-[18px] lg:grid-cols-2">
            <section
                class="rounded-[22px] border border-white/10 bg-[#171a19] p-6"
            >
                <h2 class="flex items-center gap-2 font-semibold">
                    <ShieldCheck class="size-4 text-[#02CD86]" />{{
                        t('advisor.profile.constraints')
                    }}
                </h2>
                <div class="mt-4 divide-y divide-white/8 text-sm">
                    <div
                        v-for="(value, key) in props.profile.payload
                            .constraints"
                        v-show="key !== 'hard_caps'"
                        :key="key"
                        class="flex items-center justify-between gap-4 py-3"
                    >
                        <span class="text-white/45">{{
                            String(key).replaceAll('_', ' ')
                        }}</span
                        ><strong>{{ value }}%</strong>
                    </div>
                </div>
            </section>
            <section
                class="rounded-[22px] border border-white/10 bg-[#171a19] p-6"
            >
                <h2 class="flex items-center gap-2 font-semibold">
                    <LockKeyhole class="size-4 text-[#60a5fa]" />{{
                        t('advisor.profile.options')
                    }}
                </h2>
                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-white/[0.025] p-3">
                        <span class="block text-xs text-white/35"
                            >Willingness</span
                        ><strong class="mt-1 block capitalize">{{
                            props.profile.payload.options_capability.willingness.replaceAll(
                                '_',
                                ' ',
                            )
                        }}</strong>
                    </div>
                    <div class="rounded-xl bg-white/[0.025] p-3">
                        <span class="block text-xs text-white/35"
                            >Capability</span
                        ><strong class="mt-1 block capitalize">{{
                            props.profile.payload.options_capability
                                .experience_level
                        }}</strong>
                    </div>
                    <div class="rounded-xl bg-white/[0.025] p-3">
                        <span class="block text-xs text-white/35"
                            >Knowledge</span
                        ><strong class="mt-1 block"
                            >{{
                                props.profile.payload.options_capability
                                    .knowledge_score
                            }}
                            / 100</strong
                        >
                    </div>
                    <div class="rounded-xl bg-white/[0.025] p-3">
                        <span class="block text-xs text-white/35"
                            >Risk budget</span
                        ><strong class="mt-1 block"
                            >{{
                                props.profile.payload.options_capability
                                    .maximum_risk_budget_percent
                            }}%</strong
                        >
                    </div>
                </div>
            </section>
        </div>

        <section
            class="mx-auto mt-[18px] max-w-6xl rounded-[22px] border border-white/10 bg-[#171a19] p-6"
        >
            <h2 class="font-semibold">{{ t('advisor.profile.assets') }}</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="asset in props.profile.payload.selected_assets"
                    :key="asset.asset_key"
                    class="rounded-2xl border border-white/8 bg-white/[0.025] p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-medium">{{ asset.name }}</h3>
                            <p class="mt-1 text-xs text-white/30">
                                {{ asset.category }} · {{ asset.risk_band }}
                            </p>
                        </div>
                        <CheckCircle2
                            v-if="asset.inclusion === 'required'"
                            class="size-4 text-[#02CD86]"
                        />
                    </div>
                    <p class="mt-4 text-xs text-white/45 capitalize">
                        {{ asset.perspective }} outlook · {{ asset.inclusion }}
                    </p>
                </article>
            </div>
        </section>

        <section
            class="mx-auto mt-[18px] max-w-6xl rounded-[24px] border border-[#02CD86]/20 bg-[linear-gradient(135deg,rgba(2,205,134,0.09),rgba(23,26,25,1)_50%)] p-6 md:flex md:items-center md:justify-between md:gap-8"
        >
            <div>
                <h2 class="flex items-center gap-2 text-lg font-semibold">
                    <Sparkles class="size-5 text-[#a78bfa]" />{{
                        t('advisor.ai_role')
                    }}
                </h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-white/45">
                    The AI receives this frozen profile and your selected
                    assets. CashPilot validates its complete response before you
                    see it.
                </p>
            </div>
            <Button
                class="mt-5 h-12 shrink-0 rounded-full bg-[#02CD86] px-6 font-semibold text-[#07130f] hover:bg-[#19d897] md:mt-0"
                :disabled="
                    !props.profile.ai_enabled ||
                    generator.processing ||
                    sealer.processing
                "
                @click="generateRecommendation"
                >{{ t('advisor.profile.generate') }}<ArrowRight class="size-4"
            /></Button>
        </section>
        <p
            v-if="!props.profile.ai_enabled"
            class="mx-auto mt-4 max-w-6xl text-sm text-amber-200/70"
        >
            {{ t('advisor.profile.ai_disabled') }}
        </p>
        <p
            v-if="generationError"
            class="mx-auto mt-4 max-w-6xl rounded-xl bg-red-400/10 px-4 py-3 text-sm text-red-200"
            role="alert"
        >
            {{ generationError }}
        </p>
        <p
            class="mx-auto mt-5 max-w-3xl text-center text-xs leading-5 text-white/28"
        >
            {{ t('advisor.disclosure') }}
        </p>
    </div>
</template>
