<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AdvisorPillars from '@/components/advisor/AdvisorPillars.vue';
import { usePageSubtitle } from '@/composables/usePageSubtitle';
import { useAdvisorLabels } from '@/lib/advisor/labels';
import {
    index as advisorIndex,
    profile as advisorProfile,
} from '@/routes/advisor';
import {
    show as showAssessment,
    store as storeAssessment,
} from '@/routes/advisor/assessments';
import { show as showRecommendation } from '@/routes/advisor/recommendations';

type AssessmentSummary = {
    id: number;
    status: string;
    last_completed_section: number;
    completed_at: string | null;
};
type ProfileSummary = {
    id: number;
    profile_version: number;
    created_at: string;
    ai_consent_at: string | null;
};
type RecommendationSummary = {
    id: string;
    status: string;
    mode: string;
    generated_at: string | null;
};

const props = defineProps<{
    assessment: AssessmentSummary | null;
    profile: ProfileSummary | null;
    recommendations: RecommendationSummary[];
}>();

const { t } = useI18n();
const { label } = useAdvisorLabels();
const starting = ref(false);

usePageSubtitle(() => t('advisor.home.subtitle'));

/**
 * How far into the assessment a returning user actually is.
 *
 * last_completed_section was already being sent here and never shown, so
 * "Resume" gave no clue whether two minutes of work remained or twenty — which
 * is exactly the uncertainty that stops people clicking it.
 */
const resumeSection = computed(() =>
    props.assessment?.status === 'in_progress'
        ? Math.min(8, props.assessment.last_completed_section + 1)
        : null,
);

/** `03 / 08` — the same two-digit mono form the assessment rail uses. */
const progressCounter = computed(() =>
    resumeSection.value === null
        ? null
        : `${String(resumeSection.value).padStart(2, '0')} / 08`,
);

const primaryAction = computed(() => {
    if (props.assessment?.status === 'in_progress') {
        return {
            label: t('advisor.resume_at', {
                current: resumeSection.value,
                total: 8,
            }),
            href: showAssessment(props.assessment.id).url,
        };
    }

    if (props.profile) {
        return { label: t('advisor.view_profile'), href: advisorProfile().url };
    }

    return null;
});

/** Sealed history reads as a status column, so each state needs its own ink. */
function statusColor(status: string): string {
    return (
        {
            ready: '#02cd86',
            needs_clarification: '#d9c48f',
            failed: '#e9756f',
        }[status] ?? '#686868'
    );
}

function formatDate(value: string | null): string {
    return value === null ? '—' : new Date(value).toLocaleDateString();
}

function startAssessment(): void {
    starting.value = true;
    router.post(
        storeAssessment().url,
        {},
        { onFinish: () => (starting.value = false) },
    );
}

defineOptions({
    layout: { breadcrumbs: [{ title: 'Advisor', href: advisorIndex().url }] },
});
</script>

<template>
    <Head :title="t('advisor.title')" />

    <div
        data-app-flush-bottom
        class="advisor-rise min-h-[calc(100svh-72px)] bg-background px-3.5 pt-3.5 pb-[120px] text-white lg:min-h-[calc(100svh-92px)] lg:px-7 lg:pt-[22px] lg:pb-[140px]"
    >
        <div class="advisor-rule-hero" />

        <div
            class="grid gap-[60px] pt-[38px] lg:grid-cols-[minmax(0,1fr)_300px]"
        >
            <div class="min-w-0">
                <p
                    class="advisor-mono text-[10px] tracking-[0.26em] text-[#d9c48f] uppercase"
                >
                    {{ t('advisor.eyebrow') }}
                </p>
                <h2
                    class="advisor-serif mt-[22px] max-w-[20ch] text-[36px] leading-[1.05] md:text-[52px]"
                >
                    {{ t('advisor.tagline') }}
                </h2>
                <p
                    class="mt-[22px] max-w-[52ch] text-[15.5px] leading-[1.75] text-[#989898]"
                >
                    {{ t('advisor.introduction') }}
                </p>

                <!-- Eight segments, the same eight the assessment rail lists, so
                     picking up where you left off looks like the same journey. -->
                <div v-if="resumeSection" class="mt-9 max-w-[420px]">
                    <div class="mb-2.5 flex items-baseline justify-between">
                        <span
                            class="advisor-mono text-[10px] tracking-[0.18em] text-[#686868] uppercase"
                            >{{ t('advisor.home.in_progress') }}</span
                        >
                        <span
                            class="advisor-mono advisor-figure text-[11px] text-[#d9c48f]"
                            >{{ progressCounter }}</span
                        >
                    </div>
                    <div
                        class="grid grid-cols-8 gap-[5px]"
                        role="img"
                        :aria-label="
                            t('advisor.resume_at', {
                                current: resumeSection,
                                total: 8,
                            })
                        "
                    >
                        <span
                            v-for="step in 8"
                            :key="step"
                            class="h-[3px] rounded-full"
                            :class="
                                step < resumeSection
                                    ? 'bg-[#d9c48f]'
                                    : step === resumeSection
                                      ? 'bg-[#d9c48f]/42'
                                      : 'bg-white/9'
                            "
                        />
                    </div>
                </div>

                <div class="mt-[26px] flex flex-wrap items-center gap-3">
                    <Link
                        v-if="primaryAction"
                        :href="primaryAction.href"
                        class="flex items-center gap-2.5 rounded-[10px] bg-[#02cd86] px-[26px] py-3.5 text-sm font-semibold text-[#101010] transition-colors hover:bg-[#16e19a]"
                    >
                        {{ primaryAction.label }}
                        <span class="advisor-mono text-[13px] rtl:rotate-180"
                            >→</span
                        >
                    </Link>
                    <button
                        v-else
                        type="button"
                        class="flex cursor-pointer items-center gap-2.5 rounded-[10px] bg-[#02cd86] px-[26px] py-3.5 text-sm font-semibold text-[#101010] transition-colors hover:bg-[#16e19a] disabled:opacity-60"
                        :disabled="starting"
                        @click="startAssessment"
                    >
                        {{ t('advisor.start') }}
                        <span class="advisor-mono text-[13px] rtl:rotate-180"
                            >→</span
                        >
                    </button>
                    <button
                        v-if="primaryAction"
                        type="button"
                        class="cursor-pointer rounded-[10px] border border-white/13 px-[22px] py-[13px] text-sm text-[#989898] transition-colors hover:border-white/28 hover:text-white disabled:opacity-60"
                        :disabled="starting"
                        @click="startAssessment"
                    >
                        {{ t('advisor.reassess') }}
                    </button>
                </div>

                <div
                    class="advisor-mono mt-5 flex flex-wrap gap-x-6 gap-y-2 text-[10.5px] tracking-[0.08em] text-[#5a5a5a] uppercase"
                >
                    <span>{{ t('advisor.duration') }}</span>
                    <span>{{ t('advisor.question_count') }}</span>
                </div>

                <AdvisorPillars variant="columns" class="mt-[58px]" />
            </div>

            <!-- The rail is a sidebar of history; below the shell breakpoint it
                 stops being a rail, so it is dropped rather than restacked. -->
            <aside class="hidden min-w-0 lg:block">
                <p
                    class="advisor-mono pb-3.5 text-[10px] tracking-[0.22em] text-[#686868] uppercase"
                >
                    {{ t('advisor.history') }}
                </p>
                <div class="advisor-rule">
                    <Link
                        v-for="item in props.recommendations"
                        :key="item.id"
                        :href="showRecommendation(item.id).url"
                        class="block w-full border-b border-white/7 py-[15px] text-start transition-colors hover:bg-white/[0.03] focus-visible:ring-2 focus-visible:ring-[#d9c48f] focus-visible:outline-none"
                    >
                        <span
                            class="flex items-baseline justify-between gap-2.5"
                        >
                            <span class="text-[13.5px] text-white">{{
                                item.mode === 'target_only'
                                    ? t('advisor.target_only')
                                    : t('advisor.rebalance')
                            }}</span>
                            <span
                                class="advisor-mono advisor-figure text-[10.5px] text-[#5a5a5a]"
                                >{{ formatDate(item.generated_at) }}</span
                            >
                        </span>
                        <span
                            class="advisor-mono mt-[7px] block text-[10px] tracking-[0.12em] uppercase"
                            :style="{ color: statusColor(item.status) }"
                            >{{
                                label('recommendation_statuses', item.status)
                            }}</span
                        >
                    </Link>
                </div>
                <p
                    v-if="props.recommendations.length === 0"
                    class="mt-[22px] text-[12.5px] leading-[1.7] text-[#5a5a5a]"
                >
                    {{ t('advisor.no_history') }}
                </p>
            </aside>
        </div>

        <p
            class="mx-auto mt-[70px] max-w-[68ch] text-center text-xs leading-[1.75] text-[#5a5a5a]"
        >
            {{ t('advisor.disclosure') }}
        </p>
    </div>
</template>
