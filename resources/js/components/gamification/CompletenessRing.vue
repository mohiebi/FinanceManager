<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { index as transactionsIndex } from '@/routes/transactions';
import type { Logbook } from '@/types/gamification';

const props = defineProps<{
    logbook: Logbook;
}>();

const { t } = useI18n();

const RADIUS = 54;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

/** Clamped so an over-100% month (impossible today, but cheap to guard) cannot overdraw the arc. */
const dash = computed(() => {
    const percent = Math.min(100, Math.max(0, props.logbook.percent));

    return `${(percent / 100) * CIRCUMFERENCE} ${CIRCUMFERENCE}`;
});

const uncategorisedHref = computed(() =>
    transactionsIndex.url({ query: { category: 'none' } }),
);

/** Ranks are earned by days recorded, so the hint says exactly that. */
const rankHint = computed(() =>
    props.logbook.days_to_next_rank === null
        ? t('gamification.ranks.top', { days: props.logbook.days_logged })
        : t('gamification.ranks.progress', {
              days: props.logbook.days_logged,
              remaining: props.logbook.days_to_next_rank,
          }),
);
</script>

<template>
    <article
        class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p
                class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
            >
                {{
                    t('gamification.logbook.title', {
                        month: props.logbook.month,
                    })
                }}
            </p>
            <span
                class="rounded-full bg-white/5 px-2.5 py-1 text-xs text-[#989898]"
                :title="rankHint"
            >
                {{ t(`gamification.ranks.${props.logbook.rank}`) }}
            </span>
        </div>
        <p class="mt-1 text-xs text-[#989898]">
            {{
                t('gamification.logbook.elapsed', {
                    elapsed: props.logbook.days_elapsed,
                    total: props.logbook.days_in_month,
                })
            }}
        </p>

        <div class="mt-4 flex flex-wrap items-center gap-6">
            <svg
                width="132"
                height="132"
                viewBox="0 0 132 132"
                role="img"
                :aria-label="
                    t('gamification.logbook.complete', {
                        percent: props.logbook.percent,
                    })
                "
                class="shrink-0"
            >
                <circle
                    cx="66"
                    cy="66"
                    :r="RADIUS"
                    fill="none"
                    stroke="rgba(255,255,255,0.08)"
                    stroke-width="13"
                />
                <circle
                    cx="66"
                    cy="66"
                    :r="RADIUS"
                    fill="none"
                    stroke="#02CD86"
                    stroke-width="13"
                    stroke-linecap="round"
                    :stroke-dasharray="dash"
                    transform="rotate(-90 66 66)"
                />
                <text
                    x="66"
                    y="64"
                    text-anchor="middle"
                    class="fill-white text-[26px] font-bold tabular-nums"
                >
                    {{ props.logbook.percent }}%
                </text>
                <text
                    x="66"
                    y="84"
                    text-anchor="middle"
                    class="fill-[#989898] text-[10px] tracking-[0.14em] uppercase"
                >
                    {{ t('gamification.logbook.complete_short') }}
                </text>
            </svg>

            <dl class="min-w-[190px] flex-1 space-y-2 text-sm">
                <div
                    class="flex justify-between gap-3 border-b border-white/5 pb-2"
                >
                    <dt class="text-[#989898]">
                        {{ t('gamification.logbook.days_covered') }}
                    </dt>
                    <dd class="tabular-nums">
                        {{ props.logbook.days_covered }} /
                        {{ props.logbook.days_elapsed }}
                    </dd>
                </div>
                <div
                    class="flex justify-between gap-3 border-b border-white/5 pb-2"
                >
                    <dt class="text-[#989898]">
                        {{ t('gamification.logbook.uncategorised') }}
                    </dt>
                    <dd
                        class="tabular-nums"
                        :class="
                            props.logbook.uncategorised > 0
                                ? 'text-[#02CD86]'
                                : ''
                        "
                    >
                        {{ props.logbook.uncategorised }}
                    </dd>
                </div>
                <div
                    v-if="props.logbook.bills_due !== null"
                    class="flex justify-between gap-3"
                >
                    <dt class="text-[#989898]">
                        {{ t('gamification.logbook.bills') }}
                    </dt>
                    <dd class="tabular-nums">
                        {{ props.logbook.bills_paid }} /
                        {{ props.logbook.bills_due }}
                    </dd>
                </div>
            </dl>
        </div>

        <!-- The gap is only worth showing if it is also one tap from being closed. -->
        <Link
            v-if="props.logbook.uncategorised > 0"
            :href="uncategorisedHref"
            class="mt-4 inline-block rounded-xl px-4 py-2 text-sm font-medium text-white ring-1 ring-white/15 transition hover:bg-white/5"
        >
            {{
                t(
                    'gamification.logbook.sort_uncategorised',
                    { count: props.logbook.uncategorised },
                    props.logbook.uncategorised,
                )
            }}
        </Link>
    </article>
</template>
