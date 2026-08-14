<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    BrainCircuit,
    Clock3,
    LockKeyhole,
    ShieldCheck,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
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
const starting = ref(false);
const primaryAction = computed(() => {
    if (props.assessment?.status === 'in_progress') {
        return {
            label: t('advisor.resume'),
            href: showAssessment(props.assessment.id).url,
        };
    }

    if (props.profile) {
        return { label: t('advisor.view_profile'), href: advisorProfile().url };
    }

    return null;
});

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
        class="min-h-[calc(100vh-92px)] bg-[#0d0f0f] px-[18px] py-5 text-white"
    >
        <section
            class="relative overflow-hidden rounded-[28px] border border-white/10 bg-[#171a19] px-6 py-8 shadow-[0_24px_70px_rgba(0,0,0,0.32)] md:px-10 md:py-12"
        >
            <div
                class="pointer-events-none absolute -top-32 -right-24 size-80 rounded-full bg-[#02CD86]/10 blur-3xl"
            />
            <div class="relative max-w-3xl">
                <div class="mb-5 flex items-center gap-3">
                    <span
                        class="grid size-11 place-items-center rounded-2xl bg-[#02CD86]/12 text-[#02CD86] ring-1 ring-[#02CD86]/25"
                    >
                        <BrainCircuit class="size-5" />
                    </span>
                    <div>
                        <p
                            class="text-[11px] font-semibold tracking-[0.28em] text-[#02CD86] uppercase"
                        >
                            {{ t('advisor.eyebrow') }}
                        </p>
                        <p class="mt-1 text-xs text-white/45">
                            {{ t('advisor.pro_feature') }}
                        </p>
                    </div>
                </div>

                <h1
                    class="max-w-2xl text-3xl leading-tight font-semibold tracking-[-0.03em] md:text-5xl"
                >
                    {{ t('advisor.tagline') }}
                </h1>
                <p
                    class="mt-5 max-w-2xl text-sm leading-7 text-white/55 md:text-base"
                >
                    {{ t('advisor.introduction') }}
                </p>

                <div class="mt-7 flex flex-wrap items-center gap-3">
                    <Button
                        v-if="primaryAction"
                        as-child
                        class="h-12 rounded-full bg-[#02CD86] px-6 font-semibold text-[#07130f] hover:bg-[#19d897]"
                    >
                        <Link :href="primaryAction.href"
                            >{{ primaryAction.label }}
                            <ArrowRight class="size-4"
                        /></Link>
                    </Button>
                    <Button
                        v-else
                        class="h-12 rounded-full bg-[#02CD86] px-6 font-semibold text-[#07130f] hover:bg-[#19d897]"
                        :disabled="starting"
                        @click="startAssessment"
                    >
                        {{ t('advisor.start') }} <ArrowRight class="size-4" />
                    </Button>
                    <Button
                        v-if="props.profile"
                        variant="outline"
                        class="h-12 rounded-full border-white/12 bg-white/[0.03] px-6 text-white hover:bg-white/[0.07]"
                        :disabled="starting"
                        @click="startAssessment"
                    >
                        {{ t('advisor.reassess') }}
                    </Button>
                </div>

                <div
                    class="mt-7 flex flex-wrap gap-x-5 gap-y-2 text-xs text-white/40"
                >
                    <span class="flex items-center gap-2"
                        ><Clock3 class="size-3.5 text-[#02CD86]" />{{
                            t('advisor.duration')
                        }}</span
                    >
                    <span class="flex items-center gap-2"
                        ><ShieldCheck class="size-3.5 text-[#02CD86]" />{{
                            t('advisor.question_count')
                        }}</span
                    >
                </div>
            </div>
        </section>

        <section class="mt-[18px] grid gap-[18px] lg:grid-cols-3">
            <article
                class="rounded-[22px] border border-white/10 bg-[#171a19] p-6"
            >
                <ShieldCheck class="size-5 text-[#02CD86]" />
                <h2 class="mt-5 text-lg font-semibold">
                    {{ t('advisor.cashpilot_role') }}
                </h2>
                <p class="mt-2 text-sm leading-6 text-white/48">
                    {{ t('advisor.cashpilot_role_description') }}
                </p>
            </article>
            <article
                class="rounded-[22px] border border-white/10 bg-[#171a19] p-6"
            >
                <Sparkles class="size-5 text-[#a78bfa]" />
                <h2 class="mt-5 text-lg font-semibold">
                    {{ t('advisor.ai_role') }}
                </h2>
                <p class="mt-2 text-sm leading-6 text-white/48">
                    {{ t('advisor.ai_role_description') }}
                </p>
            </article>
            <article
                class="rounded-[22px] border border-white/10 bg-[#171a19] p-6"
            >
                <LockKeyhole class="size-5 text-[#60a5fa]" />
                <h2 class="mt-5 text-lg font-semibold">
                    {{ t('advisor.vault_safe') }}
                </h2>
                <p class="mt-2 text-sm leading-6 text-white/48">
                    {{ t('advisor.vault_safe_description') }}
                </p>
            </article>
        </section>

        <section
            class="mt-[18px] rounded-[22px] border border-white/10 bg-[#171a19] p-6"
        >
            <h2 class="text-lg font-semibold">{{ t('advisor.history') }}</h2>
            <p
                v-if="props.recommendations.length === 0"
                class="mt-4 text-sm text-white/40"
            >
                {{ t('advisor.no_history') }}
            </p>
            <div v-else class="mt-4 divide-y divide-white/8">
                <Link
                    v-for="item in props.recommendations"
                    :key="item.id"
                    :href="showRecommendation(item.id).url"
                    class="group flex items-center justify-between gap-4 py-4 outline-none focus-visible:ring-2 focus-visible:ring-[#02CD86]"
                >
                    <div>
                        <p class="text-sm font-medium text-white">
                            {{
                                item.mode === 'target_only'
                                    ? t('advisor.target_only')
                                    : t('advisor.rebalance')
                            }}
                        </p>
                        <p class="mt-1 text-xs text-white/35">
                            {{ item.status }} ·
                            {{
                                item.generated_at
                                    ? new Date(
                                          item.generated_at,
                                      ).toLocaleDateString()
                                    : '—'
                            }}
                        </p>
                    </div>
                    <ArrowRight
                        class="size-4 text-white/30 transition group-hover:translate-x-1 group-hover:text-[#02CD86] rtl:group-hover:-translate-x-1"
                    />
                </Link>
            </div>
        </section>

        <p
            class="mx-auto mt-5 max-w-3xl text-center text-xs leading-5 text-white/30"
        >
            {{ t('advisor.disclosure') }}
        </p>
    </div>
</template>
