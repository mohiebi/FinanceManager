<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Lock } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AdvisorPillars from '@/components/advisor/AdvisorPillars.vue';
import { usePageSubtitle } from '@/composables/usePageSubtitle';
import { seriesColor } from '@/lib/advisor/series';
import { ringGradient } from '@/lib/advisor/series';
import { index as advisorIndex } from '@/routes/advisor';
import { edit as editBilling } from '@/routes/billing';

const props = defineProps<{
    /** True for a lapsed subscriber, whose assessment and profile still exist. */
    hasProfile: boolean;
}>();

const { t } = useI18n();
const page = usePage();

/*
 * The same kill switch the modules page reads. With billing off there is
 * nothing to sell and `billing.edit` 404s, so the CTA must not promise a plans
 * page that is not there.
 */
const billingEnabled = computed(
    () => page.props.subscription?.billing_enabled === true,
);

usePageSubtitle(() => t('advisor.paywall.subtitle'));

/**
 * A real recommendation, blurred.
 *
 * Showing the actual output is the point — an illustration would say nothing
 * about what is behind the lock. These weights are a representative plan rather
 * than the reader's own, which they do not have yet; they are illegible through
 * the blur and exist to show the shape of the thing.
 */
const previewHoldings = computed(() => [
    { name: t('advisor.paywall.preview_equity'), percent: 26 },
    { name: t('advisor.paywall.preview_gold'), percent: 22 },
    { name: t('advisor.paywall.preview_global'), percent: 18 },
    { name: t('advisor.paywall.preview_currency'), percent: 16 },
]);

const previewRing = computed(() =>
    ringGradient(
        [26, 22, 18, 16, 8, 10].map((percent, index) => ({
            percent,
            color: seriesColor(index),
        })),
    ),
);

/**
 * "See what's inside" has nothing to navigate to — the home screen it opens in
 * the prototype is exactly what this page stands in front of. It reveals the
 * same three-column explanation instead, which is what the label promises.
 */
const showsDetail = ref(false);

defineOptions({
    layout: { breadcrumbs: [{ title: 'Advisor', href: advisorIndex().url }] },
});
</script>

<template>
    <Head :title="t('advisor.title')" />

    <div
        data-app-flush-bottom
        class="min-h-[calc(100svh-72px)] bg-background px-3.5 pt-3.5 pb-[120px] text-white lg:min-h-[calc(100svh-92px)] lg:px-7 lg:pt-[22px] lg:pb-[140px]"
    >
        <div class="grid place-items-center pt-[26px]">
            <div class="advisor-rise w-full max-w-[940px]">
                <div class="advisor-rule-hero" />

                <div
                    class="grid gap-[54px] pt-10 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]"
                >
                    <div>
                        <p
                            class="advisor-mono text-[10px] tracking-[0.26em] text-[#d9c48f] uppercase"
                        >
                            {{ t('advisor.eyebrow') }}
                        </p>
                        <h2
                            class="advisor-serif mt-[22px] text-[34px] leading-[1.06] md:text-[46px]"
                        >
                            {{ t('advisor.tagline') }}
                        </h2>
                        <p
                            class="mt-5 max-w-[46ch] text-[15px] leading-[1.75] text-[#989898]"
                        >
                            {{ t('advisor.introduction') }}
                        </p>

                        <AdvisorPillars variant="rows" class="mt-[34px]" />

                        <div class="mt-8 flex flex-wrap items-center gap-3">
                            <!-- The only gold-filled button in the product. Gold
                                 is ceremony everywhere else; here it is the one
                                 place the ceremony *is* the action. -->
                            <Link
                                v-if="billingEnabled"
                                :href="editBilling()"
                                class="rounded-[10px] bg-[linear-gradient(180deg,#e6d6a8,#d9c48f)] px-[26px] py-3.5 text-sm font-semibold text-[#101010] shadow-[0_10px_30px_rgba(217,196,143,0.18)] transition-colors hover:bg-[linear-gradient(180deg,#f0e3bd,#e2cf9d)]"
                            >
                                {{ t('advisor.paywall.unlock') }}
                            </Link>
                            <button
                                type="button"
                                class="cursor-pointer rounded-[10px] border border-white/13 px-[22px] py-[13px] text-sm text-[#989898] transition-colors hover:border-white/28 hover:text-white"
                                :aria-expanded="showsDetail"
                                @click="showsDetail = !showsDetail"
                            >
                                {{ t('advisor.paywall.see_inside') }}
                            </button>
                        </div>
                        <p
                            class="advisor-mono mt-4 text-[10.5px] tracking-[0.08em] text-[#5a5a5a] uppercase"
                        >
                            {{
                                billingEnabled
                                    ? t('advisor.paywall.included')
                                    : t('modules.upgrade.unavailable')
                            }}
                        </p>

                        <!-- A returning subscriber has not lost anything. The
                             wall reads as a renewal for them, not a first
                             purchase. -->
                        <p
                            v-if="props.hasProfile"
                            class="mt-3.5 text-[13px] leading-[1.7] text-[#cfc4a6]"
                        >
                            {{ t('advisor.paywall.profile_saved') }}
                        </p>
                    </div>

                    <div
                        class="relative grid min-h-[420px] place-items-center overflow-hidden rounded-[16px] border border-white/7 bg-[linear-gradient(180deg,#252525,#131514)] p-[30px]"
                    >
                        <div
                            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_50%_34%,rgba(217,196,143,0.09),transparent_58%)]"
                            aria-hidden="true"
                        />
                        <div
                            class="flex w-full flex-col items-center gap-[22px] opacity-50 blur-[5px]"
                            aria-hidden="true"
                        >
                            <div
                                class="size-[176px] rounded-full [-webkit-mask:radial-gradient(circle,transparent_60%,#000_60.5%)] [mask:radial-gradient(circle,transparent_60%,#000_60.5%)]"
                                :style="{ background: previewRing }"
                            />
                            <div class="flex w-full flex-col gap-3">
                                <div
                                    v-for="holding in previewHoldings"
                                    :key="holding.name"
                                    class="flex justify-between border-b border-white/8 pb-2.5 last:border-b-0 last:pb-0"
                                >
                                    <span class="text-[13px]">{{
                                        holding.name
                                    }}</span>
                                    <span
                                        class="advisor-mono advisor-figure text-[13px]"
                                        >{{ holding.percent.toFixed(1) }}%</span
                                    >
                                </div>
                            </div>
                        </div>

                        <div
                            class="pointer-events-none absolute inset-0 grid place-items-center"
                        >
                            <div
                                class="advisor-seal flex flex-col items-center gap-3.5"
                            >
                                <span
                                    class="grid size-16 place-items-center rounded-full border border-[#d9c48f]/50 bg-[#111111]/72 backdrop-blur-[2px]"
                                >
                                    <Lock
                                        class="size-[22px] text-[#d9c48f]"
                                        :stroke-width="1.4"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span
                                    class="advisor-mono text-[10px] tracking-[0.2em] text-[#d9c48f] uppercase"
                                    >{{
                                        t('advisor.paywall.members_only')
                                    }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="showsDetail" class="advisor-drift mt-[58px]">
                    <AdvisorPillars variant="columns" />
                </div>

                <p
                    class="mx-auto mt-[70px] max-w-[68ch] text-center text-xs leading-[1.75] text-[#5a5a5a]"
                >
                    {{ t('advisor.disclosure') }}
                </p>
            </div>
        </div>
    </div>
</template>
