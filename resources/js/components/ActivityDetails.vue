<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Check, Plane } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatAppDate } from '@/lib/date';
import { store as storeNoSpendDay } from '@/routes/no-spend-days';
import { index as transactionsIndex } from '@/routes/transactions';
import type { StreakDayState } from '@/types/gamification';

import type { ActivitySummary } from '@/types/gamification';
const props = defineProps<
    ActivitySummary & { section: 'overview' | 'achievements' }
>();
const { t } = useI18n();
const page = usePage();
const markingNoSpend = ref(false);

const displayCalendar = computed(
    () => (page.props.calendar as string | undefined) ?? 'gregorian',
);

/** A moment's date, in whichever calendar the user actually reads. */
function displayDate(value: string): string {
    return formatAppDate(value, displayCalendar.value);
}

/**
 * One square per day, coloured by what actually happened.
 *
 * A missed day is left dark rather than marked red: the point of the strip is
 * to show a run worth continuing, not to itemise failures.
 */
const dayStyles: Record<StreakDayState, string> = {
    logged: 'bg-[#02cd86] text-[#07130f]',
    no_spend:
        'bg-[#02cd86]/22 text-[#8ff0cd] ring-1 ring-inset ring-[#02cd86]/40',
    grace: 'bg-[#3a3a3a] text-[#989898]',
    missed: 'bg-white/5 text-[#5a5a5a]',
    open: 'bg-transparent text-white ring-1 ring-inset ring-white/25',
};

const legend: StreakDayState[] = ['logged', 'no_spend', 'grace', 'open'];

/** The run this day would reach, which is the only number worth naming here. */
const runIfLoggedToday = computed(() => props.streak.current_run + 1);

const billsTracked = computed(
    () => props.logbook.bills_due !== null && props.logbook.bills_paid !== null,
);

/** Circumference at r=52, so the dash maths below reads as a fraction. */
const RING_CIRCUMFERENCE = 2 * Math.PI * 52;

const ringDash = computed(
    () => (props.logbook.percent / 100) * RING_CIRCUMFERENCE,
);

function markNoSpend(): void {
    markingNoSpend.value = true;
    router.post(
        storeNoSpendDay().url,
        {},
        { onFinish: () => (markingNoSpend.value = false) },
    );
}
</script>
<template>
    <div class="grid min-w-0 gap-[14px] xl:grid-cols-2">
        <template v-if="section === 'overview'">
            <!-- ── Current run ─────────────────────────────────────── -->
            <section
                class="rounded-[16px] border border-white/7 bg-[#1a1a1a] px-4 py-5 sm:px-6 sm:py-[22px]"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p
                        class="flex items-center gap-2.5 text-[11px] font-medium tracking-[0.16em] text-[#989898] uppercase"
                    >
                        <span
                            class="grid size-7 place-items-center rounded-[9px] bg-[#02cd86]/12 text-[#02cd86]"
                        >
                            <Plane class="size-3.5" aria-hidden="true" />
                        </span>
                        {{ t('gamification.page.current_run') }}
                    </p>
                    <span
                        class="rounded-full bg-[#02cd86]/12 px-3 py-1 text-xs text-[#8ff0cd]"
                    >
                        {{
                            props.streak.grace_remaining > 0
                                ? t(
                                      'gamification.grace_left',
                                      { count: props.streak.grace_remaining },
                                      props.streak.grace_remaining,
                                  )
                                : t('gamification.grace_none')
                        }}
                    </span>
                </div>

                <div class="mt-5 flex items-end gap-3">
                    <span
                        class="text-[52px] leading-none font-semibold tracking-[-0.04em] tabular-nums"
                        dir="ltr"
                        >{{ props.streak.current_run }}</span
                    >
                    <span class="mb-1.5">
                        <span class="block text-sm text-white">{{
                            t('gamification.page.days_without_gap')
                        }}</span>
                        <span class="mt-0.5 block text-xs text-[#989898]">{{
                            props.streak.today_would_set_record
                                ? t('gamification.page.best_and_open')
                                : t('gamification.best_run', {
                                      days: props.streak.best_run,
                                  })
                        }}</span>
                    </span>
                </div>

                <!-- The tail of the chain, oldest first, ending on today. -->
                <ol class="mt-5 flex flex-wrap gap-1.5" dir="ltr">
                    <li
                        v-for="day in props.streak.days"
                        :key="day.date"
                        class="grid h-[26px] min-w-[26px] flex-1 place-items-center rounded-[6px] px-1 text-[11px] tabular-nums"
                        :class="dayStyles[day.state]"
                        :title="t(`gamification.states.${day.state}`)"
                    >
                        {{ day.label }}
                    </li>
                </ol>

                <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5">
                    <li
                        v-for="state in legend"
                        :key="state"
                        class="flex items-center gap-1.5 text-[11px] text-[#989898]"
                    >
                        <span
                            class="size-2.5 rounded-[3px]"
                            :class="dayStyles[state]"
                            aria-hidden="true"
                        />
                        {{ t(`gamification.states.${state}`) }}
                    </li>
                </ul>

                <p class="mt-4 text-[13px] leading-6 text-[#989898]">
                    {{
                        props.streak.logged_today
                            ? t('gamification.logged_today')
                            : t('gamification.page.open_today_body', {
                                  next: runIfLoggedToday,
                              })
                    }}
                </p>

                <div
                    v-if="!props.streak.logged_today"
                    class="mt-4 flex flex-wrap items-center gap-2.5"
                >
                    <Link
                        :href="transactionsIndex()"
                        class="rounded-[10px] bg-[#02cd86] px-5 py-2.5 text-[13.5px] font-semibold text-[#07130f] transition-colors hover:bg-[#16e19a]"
                    >
                        {{ t('gamification.page.log_transaction') }}
                    </Link>
                    <button
                        type="button"
                        class="cursor-pointer rounded-[10px] border border-white/13 px-5 py-2.5 text-[13.5px] text-[#989898] transition-colors hover:border-white/28 hover:text-white disabled:opacity-50"
                        :disabled="markingNoSpend"
                        @click="markNoSpend"
                    >
                        {{ t('gamification.page.mark_no_spend') }}
                    </button>
                </div>
            </section>

            <!-- ── On record ───────────────────────────────────────── -->
            <section
                class="rounded-[16px] border border-white/7 bg-[#1a1a1a] px-4 py-5 sm:px-6 sm:py-[22px]"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-[11px] font-medium tracking-[0.16em] text-[#989898] uppercase"
                        >
                            {{
                                t('gamification.page.on_record', {
                                    month: props.logbook.month,
                                })
                            }}
                        </p>
                        <p class="mt-1 text-xs text-[#989898]">
                            {{
                                t('gamification.page.day_of', {
                                    day: props.logbook.day_of_month,
                                    total: props.logbook.days_in_month,
                                })
                            }}
                        </p>
                    </div>
                    <span
                        class="rounded-full bg-white/6 px-3 py-1 text-xs text-[#989898]"
                        >{{
                            t(`gamification.ranks.${props.logbook.rank}`)
                        }}</span
                    >
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-7">
                    <div class="relative size-[116px] shrink-0">
                        <svg
                            viewBox="0 0 120 120"
                            class="size-full -rotate-90"
                            role="img"
                            :aria-label="
                                t('gamification.logbook.complete', {
                                    percent: props.logbook.percent,
                                })
                            "
                        >
                            <circle
                                cx="60"
                                cy="60"
                                r="52"
                                fill="none"
                                stroke="rgba(255,255,255,0.07)"
                                stroke-width="9"
                            />
                            <circle
                                cx="60"
                                cy="60"
                                r="52"
                                fill="none"
                                stroke="#02cd86"
                                stroke-width="9"
                                stroke-linecap="round"
                                :stroke-dasharray="`${ringDash} ${RING_CIRCUMFERENCE}`"
                            />
                        </svg>
                        <div
                            class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center"
                        >
                            <span
                                class="text-[22px] font-semibold tabular-nums"
                                dir="ltr"
                                >{{ props.logbook.percent }}%</span
                            >
                            <span
                                class="mt-0.5 max-w-20 text-center text-[10px] text-[#989898]"
                                >{{ t('miles.days_recorded') }}</span
                            >
                        </div>
                    </div>

                    <dl class="min-w-0 flex-1 basis-[200px]">
                        <div
                            class="flex items-baseline justify-between gap-3 border-b border-white/6 py-2.5"
                        >
                            <dt class="text-[13px] text-[#989898]">
                                {{ t('gamification.logbook.days_covered') }}
                            </dt>
                            <dd class="text-[13px] tabular-nums" dir="ltr">
                                {{ props.logbook.days_covered }} /
                                {{ props.logbook.days_elapsed }}
                            </dd>
                        </div>
                        <div
                            class="flex items-baseline justify-between gap-3 border-b border-white/6 py-2.5"
                        >
                            <dt class="text-[13px] text-[#989898]">
                                {{ t('gamification.logbook.uncategorised') }}
                            </dt>
                            <dd
                                class="text-[13px] tabular-nums"
                                :class="
                                    props.logbook.uncategorised > 0
                                        ? 'text-[#f0b45f]'
                                        : ''
                                "
                                dir="ltr"
                            >
                                {{ props.logbook.uncategorised }}
                            </dd>
                        </div>
                        <div
                            v-if="billsTracked"
                            class="flex items-baseline justify-between gap-3 py-2.5"
                        >
                            <dt class="text-[13px] text-[#989898]">
                                {{ t('gamification.logbook.bills') }}
                            </dt>
                            <dd class="text-[13px] tabular-nums" dir="ltr">
                                {{ props.logbook.bills_paid }} /
                                {{ props.logbook.bills_due }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <p class="mt-4 text-[13px] leading-6 text-[#989898]">
                    {{ t('gamification.page.trust_meter') }}
                </p>

                <Link
                    v-if="props.logbook.uncategorised > 0"
                    :href="
                        transactionsIndex.url({ query: { category: 'none' } })
                    "
                    class="mt-4 inline-block rounded-[10px] border border-white/13 px-5 py-2.5 text-[13.5px] text-[#989898] transition-colors hover:border-white/28 hover:text-white"
                >
                    {{
                        t(
                            'gamification.logbook.sort_uncategorised',
                            { count: props.logbook.uncategorised },
                            props.logbook.uncategorised,
                        )
                    }}
                </Link>
            </section> </template
        ><template v-else>
            <!-- ── Rank ────────────────────────────────────────────── -->
            <section
                class="rounded-[16px] border border-white/7 bg-[#1a1a1a] px-4 py-5 sm:px-6 sm:py-[22px]"
            >
                <h2 class="text-[15px] font-medium">
                    {{ t('gamification.page.rank_title') }}
                </h2>
                <p class="mt-1.5 text-[12.5px] leading-6 text-[#989898]">
                    {{ t('gamification.page.rank_description') }}
                </p>

                <ol class="mt-4">
                    <li
                        v-for="rank in props.ranks"
                        :key="rank.key"
                        class="flex items-center justify-between gap-4 border-t border-white/6 py-3.5"
                    >
                        <span class="flex min-w-0 items-start gap-3">
                            <span
                                class="mt-1.5 size-1.5 shrink-0 rounded-full"
                                :class="
                                    rank.state === 'upcoming'
                                        ? 'bg-white/20'
                                        : 'bg-[#02cd86]'
                                "
                                aria-hidden="true"
                            />
                            <span class="min-w-0">
                                <span
                                    class="block text-sm"
                                    :class="
                                        rank.state === 'upcoming'
                                            ? 'text-[#989898]'
                                            : 'text-white'
                                    "
                                    >{{
                                        t(`gamification.ranks.${rank.key}`)
                                    }}</span
                                >
                                <span
                                    class="mt-0.5 block text-xs text-[#989898]"
                                    >{{
                                        rank.threshold === 0
                                            ? t(
                                                  'gamification.page.rank_from_first',
                                              )
                                            : t(
                                                  'gamification.page.rank_requirement',
                                                  { days: rank.threshold },
                                              )
                                    }}</span
                                >
                            </span>
                        </span>
                        <span
                            class="shrink-0 rounded-full px-3 py-1 text-xs"
                            :class="
                                rank.state === 'current'
                                    ? 'bg-[#02cd86]/12 text-[#8ff0cd]'
                                    : 'bg-white/6 text-[#989898]'
                            "
                        >
                            {{
                                rank.state === 'current'
                                    ? t('gamification.page.rank_here')
                                    : rank.state === 'passed'
                                      ? t('gamification.page.rank_passed')
                                      : t('gamification.page.rank_away', {
                                            days: rank.days_away,
                                        })
                            }}
                        </span>
                    </li>
                </ol>

                <div
                    class="mt-2 flex items-baseline justify-between gap-4 border-t border-white/6 pt-4"
                >
                    <span class="text-[13px] text-[#989898]">{{
                        t('gamification.page.days_all_time')
                    }}</span>
                    <span
                        class="text-[18px] font-semibold tabular-nums"
                        dir="ltr"
                        >{{ props.logbook.days_logged }}</span
                    >
                </div>
            </section>

            <!-- ── Moments ─────────────────────────────────────────── -->
            <section
                class="rounded-[16px] border border-white/7 bg-[#1a1a1a] px-4 py-5 sm:px-6 sm:py-[22px]"
            >
                <h2 class="text-[15px] font-medium">
                    {{ t('gamification.page.moments_title') }}
                </h2>
                <p class="mt-1.5 text-[12.5px] leading-6 text-[#989898]">
                    {{ t('gamification.page.moments_description') }}
                </p>

                <ol class="mt-4">
                    <li
                        v-for="moment in props.moments"
                        :key="moment.key"
                        class="flex items-start justify-between gap-4 border-t border-white/6 py-3.5"
                    >
                        <span class="flex min-w-0 items-start gap-3">
                            <span
                                class="mt-0.5 grid size-[18px] shrink-0 place-items-center rounded-full"
                                :class="
                                    moment.achieved_at
                                        ? 'bg-[#02cd86]/15 text-[#02cd86]'
                                        : 'border border-white/15'
                                "
                            >
                                <Check
                                    v-if="moment.achieved_at"
                                    class="size-3"
                                    aria-hidden="true"
                                />
                            </span>
                            <span class="min-w-0">
                                <span
                                    class="block text-sm"
                                    :class="
                                        moment.achieved_at
                                            ? 'text-white'
                                            : 'text-[#989898]'
                                    "
                                    >{{
                                        t(
                                            `gamification.moments.${moment.key}.title`,
                                        )
                                    }}</span
                                >
                                <span
                                    class="mt-0.5 block text-xs text-[#989898]"
                                    >{{
                                        t(
                                            `gamification.moments.${moment.key}.body`,
                                        )
                                    }}</span
                                >
                            </span>
                        </span>
                        <span
                            class="max-w-[40%] text-end text-xs text-[#989898]"
                            dir="ltr"
                            >{{
                                moment.achieved_at
                                    ? displayDate(moment.achieved_at)
                                    : moment.missed
                                      ? t('gamification.page.missed_by', {
                                            month: moment.missed.month,
                                            days: moment.missed.days,
                                        })
                                      : ''
                            }}</span
                        >
                    </li>
                </ol>
            </section>
        </template>
    </div>
</template>
