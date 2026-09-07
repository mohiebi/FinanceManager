<script setup lang="ts">
import type { RequestPayload } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ActivityDetails from '@/components/ActivityDetails.vue';
import { announceClaim } from '@/lib/miles';
import { edit as billingEdit } from '@/routes/billing';
import { claim } from '@/routes/miles';
import { store as buyFreeze } from '@/routes/miles/freezes';
import { store as repairStreak } from '@/routes/miles/repairs';
import type { ActivitySummary } from '@/types/gamification';
import type { MilesOverview } from '@/types/miles';

type HistoryEntry = {
    id: string;
    amount: number;
    balanceAfter: number;
    reason: string;
    createdAt: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

const props = defineProps<{
    overview: MilesOverview;
    activity: ActivitySummary | null;
    history: { data: HistoryEntry[]; links: PaginationLink[] };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Miles', href: '/miles' }] },
});

const { t, locale } = useI18n();
const page = usePage();
const processing = ref<string | null>(null);
const repairDate = ref('');
const copied = ref(false);
const currentOverview = computed(() =>
    page.props.miles
        ? { ...props.overview, ...page.props.miles }
        : props.overview,
);

const repairableDates = computed(
    () => currentOverview.value.protections.repairableDates,
);

/** "Yesterday" reads better than "1 days ago", and is the common case. */
function repairDayLabel(daysAgo: number): string {
    return daysAgo === 1
        ? t('miles.repair_yesterday')
        : t('miles.repair_days_ago', { count: daysAgo });
}

/** Whether the balance covers a priced action. The server still enforces it. */
function canAfford(cost: number): boolean {
    return currentOverview.value.balance >= cost;
}

/** How many more Miles a priced action needs, or zero when it is affordable. */
function shortfallFor(cost: number): number {
    return Math.max(0, cost - currentOverview.value.balance);
}

/** Collected, claimable now, or still ahead — the three states the ladder has. */
function dayClasses(day: { collected: boolean; next: boolean }): string {
    if (day.collected) {
        return 'border-[#02cd86]/28 bg-[#02cd86]/8';
    }

    return day.next
        ? 'border-[#02cd86]/55 bg-[#02cd86]/[0.04]'
        : 'border-white/8';
}

function paginationLabel(label: string): string {
    return label.replace('&laquo;', '‹').replace('&raquo;', '›');
}

function post(
    key: string,
    url: string,
    data: RequestPayload = {},
    onSuccess?: () => void,
): void {
    if (processing.value !== null) {
        return;
    }

    processing.value = key;
    router.post(url, data, {
        preserveScroll: true,
        only: ['overview', 'miles', 'history', 'milesClaim', 'activity'],
        onSuccess,
        onFinish: () => {
            processing.value = null;
        },
    });
}

function collect(): void {
    post('claim', claim.url(), {}, () => announceClaim(page.props.milesClaim));
}

function purchaseFreeze(): void {
    post('freeze', buyFreeze.url());
}

function repair(): void {
    if (repairDate.value) {
        post('repair', repairStreak.url(), { date: repairDate.value });
    }
}

async function copyReferralLink(): Promise<void> {
    await navigator.clipboard.writeText(props.overview.referralUrl);
    copied.value = true;
    window.setTimeout(() => (copied.value = false), 1800);
}

function humanize(value: string): string {
    return value
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
</script>

<template>
    <main
        class="flex flex-col gap-[14px] px-4 py-5 text-white sm:px-6 lg:px-8 lg:py-7"
    >
        <Head :title="t('miles.activity_title')" />
        <!-- Balance. Gold is scoped to Miles: it marks the currency, and the
             debit rows in history are the only other place it appears. -->
        <section
            class="rounded-[16px] border border-[#d9c48f]/22 bg-[linear-gradient(135deg,#1c1a12_0%,#131313_55%,#171717_100%)] px-4 py-5 sm:px-6 sm:py-[22px]"
        >
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <div
                        class="text-[11px] font-medium tracking-[0.13em] text-[#d9c48f] uppercase"
                    >
                        {{ t('miles.balance_eyebrow') }}
                    </div>
                    <div class="mt-2 flex items-baseline gap-[9px]">
                        <span
                            dir="ltr"
                            class="text-[40px] font-semibold tracking-[-0.02em]"
                            >{{
                                currentOverview.balance.toLocaleString()
                            }}</span
                        >
                        <span class="text-sm text-[#989898]">{{
                            t('miles.title')
                        }}</span>
                    </div>
                    <p
                        class="mt-2 max-w-[46ch] text-[13px] leading-[1.6] text-[#989898]"
                    >
                        {{ t('miles.hero_body') }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Link
                        v-if="page.props.subscription?.billing_enabled"
                        :href="billingEdit()"
                        class="inline-flex min-h-11 items-center rounded-[10px] border border-[#d9c48f]/30 px-4 text-sm text-[#d9c48f] transition-colors hover:bg-[#d9c48f]/10 focus-visible:ring-2 focus-visible:ring-[#d9c48f] focus-visible:outline-none"
                        >{{ t('miles.buy_miles') }}</Link
                    >
                    <button
                        v-if="currentOverview.claimable"
                        type="button"
                        class="min-h-11 shrink-0 cursor-pointer rounded-[10px] bg-[#02cd86] px-[18px] py-3 text-[13.5px] font-medium whitespace-nowrap text-[#101010] transition-colors hover:bg-[#14e096] focus-visible:ring-2 focus-visible:ring-[#5eeeb5] focus-visible:outline-none disabled:cursor-wait disabled:opacity-60 motion-reduce:transition-none"
                        :disabled="processing !== null"
                        @click="collect"
                    >
                        {{
                            t('miles.collect_today', {
                                miles: currentOverview.nextClaimReward,
                            })
                        }}
                    </button>
                    <div
                        v-else
                        class="flex min-h-11 shrink-0 items-center gap-2 rounded-[10px] bg-white/5 px-[18px] text-[13.5px] text-[#5eeeb5] ring-1 ring-white/10"
                    >
                        <Check class="size-4" aria-hidden="true" />
                        {{ t('miles.claimed_today') }}
                    </div>
                </div>
            </div>
        </section>

        <!-- Daily claim ladder -->
        <section
            class="rounded-[16px] border border-white/8 bg-[#1a1a1a] px-3 py-5 sm:px-[22px]"
        >
            <div class="mb-4 flex items-baseline justify-between gap-3">
                <h2 class="text-[14.5px] font-medium">
                    {{ t('miles.daily_claim') }}
                </h2>
                <span
                    class="rounded-full bg-white/5 px-[11px] py-1 text-xs text-[#989898]"
                >
                    {{
                        t('miles.day_of_seven', {
                            step: currentOverview.claimStep,
                        })
                    }}
                </span>
            </div>
            <ol class="grid grid-cols-7 gap-1 sm:gap-2">
                <li
                    v-for="day in currentOverview.claimCycle"
                    :key="day.step"
                    class="rounded-[12px] border px-1 py-3 text-center"
                    :class="dayClasses(day)"
                >
                    <span
                        class="block text-[10px] whitespace-nowrap text-[#989898] sm:text-[10.5px]"
                        >{{ t('miles.day', { day: day.step }) }}</span
                    >
                    <span
                        dir="ltr"
                        class="mt-0.5 block text-[15px] font-semibold"
                        :class="
                            day.collected || day.next
                                ? 'text-[#5eeeb5]'
                                : 'text-[#989898]'
                        "
                        >+{{ day.reward }}</span
                    >
                </li>
            </ol>
        </section>

        <ActivityDetails v-if="activity" v-bind="activity" section="overview" />
        <!-- Protections and growth. Spend actions stay neutral; inviting gets
             the green because it is the one that grows the app. -->
        <section
            class="grid items-start gap-[14px] md:grid-cols-2 xl:grid-cols-3"
        >
            <article
                class="rounded-[16px] border border-white/8 bg-[#1a1a1a] px-[22px] py-5"
            >
                <h2 class="mb-[3px] text-[14.5px] font-medium">
                    {{ t('miles.freeze_title') }}
                </h2>
                <p class="mb-[14px] text-[12.5px] text-[#989898]">
                    {{
                        t('miles.freeze_body_held', {
                            held: currentOverview.freezesHeld,
                            maximum: currentOverview.freezesMaximum,
                        })
                    }}
                </p>
                <button
                    type="button"
                    class="min-h-11 w-full cursor-pointer rounded-[10px] bg-white/6 px-[15px] py-[10px] text-[13.5px] text-white transition-colors hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-[#02cd86] focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-45 motion-reduce:transition-none"
                    :disabled="
                        processing !== null ||
                        currentOverview.freezesHeld >=
                            currentOverview.freezesMaximum ||
                        !canAfford(props.overview.protections.freezePrice)
                    "
                    @click="purchaseFreeze"
                >
                    {{
                        t('miles.buy_for', {
                            miles: props.overview.protections.freezePrice,
                        })
                    }}
                </button>
                <p
                    v-if="
                        shortfallFor(props.overview.protections.freezePrice) > 0
                    "
                    class="mt-2 text-xs text-[#989898]"
                >
                    {{
                        t('miles.need_more', {
                            miles: shortfallFor(
                                props.overview.protections.freezePrice,
                            ),
                        })
                    }}
                </p>
            </article>

            <article
                class="rounded-[16px] border border-white/8 bg-[#1a1a1a] px-[22px] py-5"
            >
                <h2 class="mb-[3px] text-[14.5px] font-medium">
                    {{ t('miles.repair_title') }}
                </h2>
                <p class="mb-[14px] text-[12.5px] text-[#989898]">
                    {{
                        t('miles.repair_body_left', {
                            remaining:
                                props.overview.protections.repairsRemaining,
                        })
                    }}
                </p>
                <!-- The user's own missed days, not a generic last seven: a
                     day that needs no mending would be refused after the click.
                     -->
                <label class="sr-only" for="miles-repair-date">{{
                    t('miles.repair_choose_day')
                }}</label>
                <select
                    v-if="repairableDates.length > 0"
                    id="miles-repair-date"
                    v-model="repairDate"
                    class="mb-2 min-h-11 w-full rounded-[10px] border border-white/10 bg-[#212121] px-3 text-[13px] text-white focus:border-[#02cd86] focus:outline-none"
                >
                    <option value="">{{ t('miles.repair_choose_day') }}</option>
                    <option
                        v-for="day in repairableDates"
                        :key="day.date"
                        :value="day.date"
                    >
                        {{ repairDayLabel(day.daysAgo) }}
                    </option>
                </select>
                <p v-else class="mb-2 text-xs text-[#989898]">
                    {{
                        t('miles.repair_nothing_missed', {
                            days: props.overview.protections.repairWindowDays,
                        })
                    }}
                </p>
                <button
                    type="button"
                    class="min-h-11 w-full cursor-pointer rounded-[10px] bg-white/6 px-[15px] py-[10px] text-[13.5px] text-white transition-colors hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-[#02cd86] focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-45 motion-reduce:transition-none"
                    :disabled="
                        processing !== null ||
                        !repairDate ||
                        repairableDates.length === 0 ||
                        !canAfford(props.overview.protections.repairPrice)
                    "
                    @click="repair"
                >
                    {{
                        t('miles.buy_for', {
                            miles: props.overview.protections.repairPrice,
                        })
                    }}
                </button>
                <p
                    v-if="
                        shortfallFor(props.overview.protections.repairPrice) > 0
                    "
                    class="mt-2 text-xs text-[#989898]"
                >
                    {{
                        t('miles.need_more', {
                            miles: shortfallFor(
                                props.overview.protections.repairPrice,
                            ),
                        })
                    }}
                </p>
            </article>

            <article
                class="rounded-[16px] border border-white/8 bg-[#1a1a1a] px-[22px] py-5"
            >
                <h2 class="mb-[3px] text-[14.5px] font-medium">
                    {{ t('miles.invite_card_title') }}
                </h2>
                <p class="mb-[14px] text-[12.5px] text-[#989898]">
                    {{ t('miles.invite_card_body') }}
                </p>
                <button
                    type="button"
                    class="min-h-11 w-full cursor-pointer rounded-[10px] bg-[#02cd86] px-[15px] py-[10px] text-[13.5px] font-medium text-[#101010] transition-colors hover:bg-[#14e096] focus-visible:ring-2 focus-visible:ring-[#5eeeb5] focus-visible:outline-none motion-reduce:transition-none"
                    @click="copyReferralLink"
                >
                    {{ copied ? t('miles.copied') : t('miles.copy_invite') }}
                </button>
            </article>
        </section>

        <ActivityDetails
            v-if="activity"
            v-bind="activity"
            section="achievements"
        />
        <!-- Unlock with Miles -->
        <section
            class="rounded-[16px] border border-white/8 bg-[#1a1a1a] px-[22px] py-5"
        >
            <h2 class="mb-[3px] text-[14.5px] font-medium">
                {{ t('miles.unlock_title') }}
            </h2>
            <p class="mb-4 text-[12.5px] text-[#989898]">
                {{
                    t('miles.unlock_body', {
                        price: props.overview.modules[0]?.price ?? 25,
                    })
                }}
            </p>
            <ul class="flex flex-col">
                <li
                    v-for="module in props.overview.modules"
                    :key="module.key"
                    class="flex items-center gap-3 border-t border-white/6 py-3"
                >
                    <span class="min-w-0 flex-1 text-sm">{{
                        module.label
                    }}</span>
                    <span
                        class="rounded-full px-[11px] py-1 text-xs"
                        :class="
                            module.unlocked
                                ? 'bg-[#02cd86]/13 text-[#5eeeb5]'
                                : 'bg-white/5 text-[#989898]'
                        "
                    >
                        {{
                            module.unlocked
                                ? t('miles.unlocked')
                                : t('miles.unlock_price', {
                                      price: module.price,
                                  })
                        }}
                    </span>
                </li>
            </ul>
        </section>

        <!-- History -->
        <section
            class="app-scroll-thin overflow-x-auto rounded-[16px] border border-white/8 bg-[#1a1a1a] px-[22px] pt-5 pb-[10px]"
        >
            <div class="mb-1.5 min-w-[560px]">
                <h2 class="mb-[3px] text-[14.5px] font-medium">
                    {{ t('miles.history_heading') }}
                </h2>
                <p class="text-[12.5px] text-[#989898]">
                    {{ t('miles.no_expiry') }}
                </p>
            </div>

            <div
                class="grid min-w-[560px] grid-cols-[minmax(160px,1fr)_130px_90px_90px] items-center gap-3 pt-3.5 pb-2.5 text-[10px] font-medium tracking-[0.1em] text-[#989898] uppercase"
            >
                <div>{{ t('miles.history_reason') }}</div>
                <div class="text-end">{{ t('miles.history_date') }}</div>
                <div class="text-end">{{ t('miles.history_amount') }}</div>
                <div class="text-end">{{ t('miles.history_balance') }}</div>
            </div>

            <div
                v-for="entry in props.history.data"
                :key="entry.id"
                class="grid min-w-[560px] grid-cols-[minmax(160px,1fr)_130px_90px_90px] items-center gap-3 border-t border-white/6 py-[13px]"
            >
                <span class="text-sm">{{ humanize(entry.reason) }}</span>
                <span dir="ltr" class="text-end text-xs text-[#989898]">{{
                    formatDate(entry.createdAt)
                }}</span>
                <!-- Gold, not red: spending Miles is the point of having them,
                     not an error. -->
                <span
                    dir="ltr"
                    class="text-end text-[13px] font-medium tabular-nums"
                    :class="
                        entry.amount > 0 ? 'text-[#5eeeb5]' : 'text-[#d9c48f]'
                    "
                    >{{ entry.amount > 0 ? '+' : '' }}{{ entry.amount }}</span
                >
                <span
                    dir="ltr"
                    class="text-end text-[13px] text-[#989898] tabular-nums"
                    >{{ entry.balanceAfter }}</span
                >
            </div>

            <p
                v-if="props.history.data.length === 0"
                class="border-t border-white/6 py-[13px] text-sm text-[#989898]"
            >
                {{ t('miles.history_empty') }}
            </p>

            <nav
                v-if="props.history.links.length > 3"
                class="flex flex-wrap gap-1 border-t border-white/6 py-3"
            >
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="link in props.history.links"
                    :key="link.label"
                    :href="link.url ?? undefined"
                    class="grid min-h-9 min-w-9 place-items-center rounded-lg px-2 text-xs"
                    :class="
                        link.active
                            ? 'bg-[#02cd86] text-[#101010]'
                            : link.url
                              ? 'cursor-pointer text-[#989898] hover:bg-white/5'
                              : 'text-[#4d4d4d]'
                    "
                >
                    {{ paginationLabel(link.label) }}
                </component>
            </nav>
        </section>
    </main>
</template>
