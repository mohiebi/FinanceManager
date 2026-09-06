<script setup lang="ts">
import type { RequestPayload } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Award,
    Check,
    CircleGauge,
    Copy,
    Gift,
    Palette,
    ShieldCheck,
    Sparkles,
    UserPlus,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNavigationNaming } from '@/composables/useNavigationNaming';
import { announceClaim } from '@/lib/miles';
import { claim } from '@/routes/miles';
import { store as buyCosmetic } from '@/routes/miles/cosmetics';
import { store as buyFreeze } from '@/routes/miles/freezes';
import { store as sendGift } from '@/routes/miles/gifts';
import { store as repairStreak } from '@/routes/miles/repairs';
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
    history: { data: HistoryEntry[]; links: PaginationLink[] };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Miles', href: '/miles' }] },
});

const { t, te, locale } = useI18n();
const { flightTerminologyEnabled, navigationName } = useNavigationNaming();
const page = usePage();
const processing = ref<string | null>(null);
const repairDate = ref('');
const giftRecipient = ref<number | null>(null);
const giftAmount = ref(25);
const copied = ref(false);
const currentOverview = computed(() =>
    page.props.miles
        ? { ...props.overview, ...page.props.miles }
        : props.overview,
);

/** The completed-cycles line, in whichever vocabulary the user chose. */
function cyclesCompletedLabel(values: Record<string, number>): string {
    return t(
        flightTerminologyEnabled.value
            ? 'miles.cycles_completed_flight'
            : 'miles.cycles_completed',
        values,
    );
}

/** Whether the balance covers a priced action. The server still enforces it. */
function canAfford(cost: number): boolean {
    return currentOverview.value.balance >= cost;
}

/**
 * Config authors cosmetics in English. Fall back to the configured label so an
 * untranslated addition shows its name rather than a raw key.
 */
function cosmeticLabel(cosmetic: { key: string; label: string }): string {
    const key = `miles.cosmetic_labels.${cosmetic.key}`;

    return te(key) ? t(key) : cosmetic.label;
}

function cosmeticType(type: string): string {
    const key = `miles.cosmetic_types.${type}`;

    return te(key) ? t(key) : humanize(type);
}

/** How many more Miles a priced action needs, or zero when it is affordable. */
function shortfallFor(cost: number): number {
    return Math.max(0, cost - currentOverview.value.balance);
}

function paginationLabel(label: string): string {
    return label.replace('&laquo;', '‹').replace('&raquo;', '›');
}

const earnedMilestones = computed(
    () =>
        props.overview.milestones.filter(
            (milestone) => milestone.achievedAt !== null,
        ).length,
);

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
        only: ['overview', 'miles', 'history', 'milesClaim'],
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

function purchaseCosmetic(key: string): void {
    post(`cosmetic:${key}`, buyCosmetic.url(key));
}

function giftMiles(): void {
    if (giftRecipient.value !== null) {
        post('gift', sendGift.url(), {
            recipient_id: giftRecipient.value,
            amount: giftAmount.value,
        });
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
    <Head :title="t('miles.title')" />

    <main class="px-4 py-5 text-white sm:px-6 lg:px-8 lg:py-7">
        <section
            class="relative overflow-hidden rounded-3xl border border-[#02cd86]/20 bg-[linear-gradient(135deg,#14231d_0%,#111111_55%,#171717_100%)] p-5 shadow-[0_24px_80px_rgba(0,0,0,0.24)] sm:p-7"
        >
            <div
                class="pointer-events-none absolute -end-16 -top-20 size-64 rounded-full bg-[#02cd86]/10 blur-3xl"
            />
            <div
                class="relative grid gap-6 lg:grid-cols-[1fr_auto] lg:items-end"
            >
                <div>
                    <div
                        class="flex items-center gap-2 text-sm font-medium text-[#5eeeb5]"
                    >
                        <CircleGauge class="size-4" aria-hidden="true" />
                        {{ t('miles.eyebrow') }}
                    </div>
                    <p
                        class="mt-3 text-4xl font-bold tracking-[-0.04em] sm:text-5xl"
                    >
                        {{ currentOverview.balance.toLocaleString() }}
                        <span
                            class="text-xl font-medium tracking-normal text-[#989898]"
                            >{{ t('miles.title') }}</span
                        >
                    </p>
                    <p
                        class="mt-3 max-w-2xl text-sm leading-6 text-[#a3a3a3] sm:text-base"
                    >
                        {{ t('miles.intro') }}
                    </p>
                </div>

                <button
                    v-if="currentOverview.claimable"
                    type="button"
                    class="inline-flex min-h-12 cursor-pointer items-center justify-center gap-2 rounded-2xl bg-[#02cd86] px-6 text-sm font-bold text-[#07130e] transition-colors duration-200 hover:bg-[#32dda0] focus-visible:ring-2 focus-visible:ring-[#5eeeb5] focus-visible:ring-offset-2 focus-visible:ring-offset-[#111] focus-visible:outline-none disabled:cursor-wait disabled:opacity-60 motion-reduce:transition-none"
                    :disabled="processing !== null"
                    @click="collect"
                >
                    <Sparkles class="size-4" aria-hidden="true" />
                    {{
                        t('miles.claim', {
                            miles: currentOverview.nextClaimReward,
                        })
                    }}
                </button>
                <div
                    v-else
                    class="flex min-h-12 items-center gap-2 rounded-2xl bg-white/5 px-5 text-sm text-[#5eeeb5] ring-1 ring-white/10"
                >
                    <Check class="size-4" aria-hidden="true" />
                    {{ t('miles.claimed_today') }}
                </div>
            </div>
        </section>

        <div
            class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.75fr)]"
        >
            <div class="space-y-5">
                <section
                    class="rounded-3xl border border-white/8 bg-[#151515] p-5 sm:p-6"
                >
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold">
                                {{ t('miles.cycle_title') }}
                            </h2>
                            <p class="mt-1 text-sm text-[#989898]">
                                {{
                                    t('miles.cycle_progress', {
                                        count: currentOverview.claimProgress,
                                    })
                                }}
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-white/5 px-3 py-1 text-xs text-[#b5b5b5]"
                        >
                            {{ t('miles.cycle_total') }}
                        </span>
                    </div>

                    <ol
                        class="mt-5 grid grid-cols-4 gap-2 sm:grid-cols-7"
                        :aria-label="t('miles.cycle_title')"
                    >
                        <li
                            v-for="day in props.overview.claimCycle"
                            :key="day.step"
                            class="relative flex min-h-24 flex-col items-center justify-center rounded-2xl border px-2 text-center"
                            :class="
                                day.collected
                                    ? 'border-[#02cd86]/30 bg-[#02cd86]/10'
                                    : day.next
                                      ? 'border-[#5eeeb5]/55 bg-[#02cd86]/5'
                                      : 'border-white/8 bg-white/[0.025]'
                            "
                        >
                            <span class="text-[11px] text-[#858585]">{{
                                t('miles.day', { day: day.step })
                            }}</span>
                            <Check
                                v-if="day.collected"
                                class="mt-2 size-5 text-[#02cd86]"
                                aria-hidden="true"
                            />
                            <Sparkles
                                v-else
                                class="mt-2 size-5"
                                :class="
                                    day.next ? 'text-[#5eeeb5]' : 'text-[#555]'
                                "
                                aria-hidden="true"
                            />
                            <span class="mt-1 text-sm font-semibold"
                                >+{{ day.reward }}</span
                            >
                        </li>
                    </ol>
                </section>

                <section class="grid gap-4 sm:grid-cols-2">
                    <article
                        class="rounded-3xl border border-white/8 bg-[#151515] p-5"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span
                                class="grid size-10 place-items-center rounded-xl bg-[#02cd86]/10 text-[#02cd86]"
                            >
                                <ShieldCheck
                                    class="size-5"
                                    aria-hidden="true"
                                />
                            </span>
                            <span class="text-xs text-[#858585]"
                                >{{ currentOverview.freezesHeld }}/{{
                                    currentOverview.freezesMaximum
                                }}</span
                            >
                        </div>
                        <h2 class="mt-4 font-semibold">
                            {{ t('miles.freeze_title') }}
                        </h2>
                        <p
                            class="mt-2 min-h-12 text-sm leading-6 text-[#989898]"
                        >
                            {{ t('miles.freeze_body') }}
                        </p>
                        <button
                            type="button"
                            class="mt-4 min-h-11 w-full cursor-pointer rounded-xl bg-white/8 px-4 text-sm font-semibold transition-colors hover:bg-white/12 focus-visible:ring-2 focus-visible:ring-[#02cd86] focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-45"
                            :disabled="
                                processing !== null ||
                                currentOverview.freezesHeld >=
                                    currentOverview.freezesMaximum ||
                                !canAfford(
                                    props.overview.protections.freezePrice,
                                )
                            "
                            @click="purchaseFreeze"
                        >
                            {{
                                t('miles.buy_for', {
                                    miles: props.overview.protections
                                        .freezePrice,
                                })
                            }}
                        </button>
                        <p
                            v-if="
                                shortfallFor(
                                    props.overview.protections.freezePrice,
                                ) > 0
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
                        class="rounded-3xl border border-white/8 bg-[#151515] p-5"
                    >
                        <span
                            class="grid size-10 place-items-center rounded-xl bg-[#d9c48f]/10 text-[#d9c48f]"
                        >
                            <Award class="size-5" aria-hidden="true" />
                        </span>
                        <h2 class="mt-4 font-semibold">
                            {{ t('miles.repair_title') }}
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-[#989898]">
                            {{ t('miles.repair_body') }}
                        </p>
                        <label
                            class="mt-3 block text-xs text-[#b5b5b5]"
                            for="repair-date"
                            >{{ t('miles.missed_date') }}</label
                        >
                        <div class="mt-1 flex gap-2">
                            <input
                                id="repair-date"
                                v-model="repairDate"
                                type="date"
                                class="min-h-11 min-w-0 flex-1 rounded-xl border border-white/10 bg-[#0f0f0f] px-3 text-sm text-white focus:border-[#02cd86] focus:ring-1 focus:ring-[#02cd86] focus:outline-none"
                            />
                            <button
                                type="button"
                                class="min-h-11 cursor-pointer rounded-xl bg-white/8 px-3 text-sm font-semibold transition-colors hover:bg-white/12 focus-visible:ring-2 focus-visible:ring-[#02cd86] focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-45"
                                :disabled="
                                    processing !== null ||
                                    !repairDate ||
                                    !canAfford(
                                        props.overview.protections.repairPrice,
                                    )
                                "
                                @click="repair"
                            >
                                {{ props.overview.protections.repairPrice }}
                            </button>
                        </div>
                        <p
                            v-if="
                                shortfallFor(
                                    props.overview.protections.repairPrice,
                                ) > 0
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
                </section>

                <section
                    v-if="props.overview.cosmeticsEnabled"
                    class="rounded-3xl border border-white/8 bg-[#151515] p-5 sm:p-6"
                >
                    <div class="flex items-center gap-3">
                        <Palette
                            class="size-5 text-[#a89bf3]"
                            aria-hidden="true"
                        />
                        <div>
                            <h2 class="font-semibold">
                                {{
                                    navigationName(
                                        'miles.cosmetics_title',
                                        'miles.cosmetics_title_flight',
                                    )
                                }}
                            </h2>
                            <p class="mt-1 text-sm text-[#989898]">
                                {{ t('miles.cosmetics_body') }}
                            </p>
                        </div>
                    </div>
                    <ul class="mt-5 grid gap-3 sm:grid-cols-2">
                        <li
                            v-for="cosmetic in props.overview.cosmetics"
                            :key="cosmetic.key"
                            class="flex items-center gap-3 rounded-2xl border border-white/8 bg-white/[0.025] p-4"
                        >
                            <span
                                class="grid size-10 place-items-center rounded-xl bg-[#a89bf3]/10 text-[#a89bf3]"
                            >
                                <Sparkles class="size-4" aria-hidden="true" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">
                                    {{ cosmeticLabel(cosmetic) }}
                                </p>
                                <p class="text-xs text-[#858585]">
                                    {{ cosmeticType(cosmetic.type) }}
                                </p>
                                <p
                                    v-if="
                                        !cosmetic.owned &&
                                        shortfallFor(cosmetic.price) > 0
                                    "
                                    class="mt-0.5 text-xs text-[#858585]"
                                >
                                    {{
                                        t('miles.need_more', {
                                            miles: shortfallFor(cosmetic.price),
                                        })
                                    }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="min-h-11 cursor-pointer rounded-xl px-3 text-xs font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-[#a89bf3] focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                                :class="
                                    cosmetic.selected
                                        ? 'bg-[#02cd86]/10 text-[#5eeeb5]'
                                        : 'bg-white/8 text-white hover:bg-white/12'
                                "
                                :disabled="
                                    processing !== null ||
                                    cosmetic.selected ||
                                    (!cosmetic.owned &&
                                        !canAfford(cosmetic.price))
                                "
                                @click="purchaseCosmetic(cosmetic.key)"
                            >
                                {{
                                    cosmetic.selected
                                        ? t('miles.equipped')
                                        : cosmetic.owned
                                          ? t('miles.equip')
                                          : `${cosmetic.price}`
                                }}
                            </button>
                        </li>
                    </ul>
                </section>
            </div>

            <aside class="space-y-5">
                <section
                    class="rounded-3xl border border-white/8 bg-[#151515] p-5"
                >
                    <div class="flex items-center gap-3">
                        <Award
                            class="size-5 text-[#d9c48f]"
                            aria-hidden="true"
                        />
                        <h2 class="font-semibold">
                            {{
                                navigationName(
                                    'miles.streak_title',
                                    'miles.streak_title_flight',
                                )
                            }}
                        </h2>
                    </div>
                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-2xl bg-white/[0.035] p-4">
                            <p class="text-3xl font-bold">
                                {{ props.overview.streak.current_run }}
                            </p>
                            <p class="mt-1 text-xs text-[#858585]">
                                {{ t('miles.current_run') }}
                            </p>
                        </div>
                        <div class="rounded-2xl bg-white/[0.035] p-4">
                            <p class="text-3xl font-bold">
                                {{ props.overview.streak.best_run }}
                            </p>
                            <p class="mt-1 text-xs text-[#858585]">
                                {{ t('miles.best_run') }}
                            </p>
                        </div>
                    </div>
                    <p class="mt-4 text-sm text-[#989898]">
                        {{
                            cyclesCompletedLabel({
                                count: props.overview.completedCycles,
                            })
                        }}
                    </p>
                </section>

                <section
                    class="rounded-3xl border border-white/8 bg-[#151515] p-5"
                >
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-semibold">
                            {{ t('miles.milestones_title') }}
                        </h2>
                        <span class="text-xs text-[#858585]"
                            >{{ earnedMilestones }}/{{
                                props.overview.milestones.length
                            }}</span
                        >
                    </div>
                    <ul
                        class="app-scroll-thin mt-4 max-h-72 space-y-2 overflow-y-auto pe-1"
                    >
                        <li
                            v-for="milestone in props.overview.milestones"
                            :key="milestone.key"
                            class="flex items-center gap-3 rounded-xl px-2 py-2"
                            :class="
                                milestone.achievedAt
                                    ? 'bg-[#02cd86]/6'
                                    : 'opacity-55'
                            "
                        >
                            <span
                                class="grid size-8 place-items-center rounded-lg"
                                :class="
                                    milestone.achievedAt
                                        ? 'bg-[#02cd86]/12 text-[#02cd86]'
                                        : 'bg-white/5 text-[#777]'
                                "
                            >
                                <Check
                                    v-if="milestone.achievedAt"
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                <Award
                                    v-else
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </span>
                            <span class="min-w-0 flex-1 truncate text-sm">{{
                                humanize(milestone.key)
                            }}</span>
                            <span class="text-xs font-semibold text-[#5eeeb5]"
                                >+{{ milestone.miles }}</span
                            >
                        </li>
                    </ul>
                </section>

                <section
                    class="rounded-3xl border border-white/8 bg-[#151515] p-5"
                >
                    <div class="flex items-center gap-3">
                        <UserPlus
                            class="size-5 text-[#02cd86]"
                            aria-hidden="true"
                        />
                        <h2 class="font-semibold">
                            {{ t('miles.invite_title') }}
                        </h2>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-[#989898]">
                        {{ t('miles.invite_body') }}
                    </p>
                    <button
                        type="button"
                        class="mt-4 flex min-h-11 w-full cursor-pointer items-center justify-between gap-3 rounded-xl border border-white/10 bg-[#0f0f0f] px-3 text-start text-xs transition-colors hover:border-[#02cd86]/40 focus-visible:ring-2 focus-visible:ring-[#02cd86] focus-visible:outline-none"
                        @click="copyReferralLink"
                    >
                        <span class="truncate text-[#b5b5b5]">{{
                            props.overview.referralUrl
                        }}</span>
                        <span
                            class="flex shrink-0 items-center gap-1 text-[#5eeeb5]"
                        >
                            <Check v-if="copied" class="size-3.5" />
                            <Copy v-else class="size-3.5" />
                            {{ copied ? t('miles.copied') : t('miles.copy') }}
                        </span>
                    </button>

                    <form
                        v-if="
                            props.overview.giftingEnabled &&
                            props.overview.referrals.length
                        "
                        class="mt-4 border-t border-white/8 pt-4"
                        @submit.prevent="giftMiles"
                    >
                        <label
                            class="text-xs text-[#b5b5b5]"
                            for="gift-recipient"
                            >{{ t('miles.gift_title') }}</label
                        >
                        <select
                            id="gift-recipient"
                            v-model="giftRecipient"
                            class="mt-1 min-h-11 w-full rounded-xl border border-white/10 bg-[#0f0f0f] px-3 text-sm focus:border-[#02cd86] focus:outline-none"
                        >
                            <option :value="null" disabled>
                                {{ t('miles.choose_friend') }}
                            </option>
                            <option
                                v-for="referral in props.overview.referrals"
                                :key="referral.id"
                                :value="referral.userId"
                            >
                                {{ referral.name }}
                            </option>
                        </select>
                        <div class="mt-2 flex gap-2">
                            <select
                                v-model="giftAmount"
                                class="min-h-11 flex-1 rounded-xl border border-white/10 bg-[#0f0f0f] px-3 text-sm focus:border-[#02cd86] focus:outline-none"
                            >
                                <option :value="25">25 Miles</option>
                                <option :value="50">50 Miles</option>
                            </select>
                            <button
                                type="submit"
                                class="grid min-h-11 min-w-11 cursor-pointer place-items-center rounded-xl bg-[#02cd86] px-4 text-[#07130e] focus-visible:ring-2 focus-visible:ring-[#5eeeb5] focus-visible:outline-none disabled:opacity-50"
                                :disabled="
                                    processing !== null ||
                                    giftRecipient === null ||
                                    !canAfford(giftAmount)
                                "
                            >
                                <Gift class="size-4" />
                                <span class="sr-only">{{
                                    t('miles.send_gift')
                                }}</span>
                            </button>
                        </div>
                    </form>
                </section>
            </aside>
        </div>

        <section
            class="mt-5 rounded-3xl border border-white/8 bg-[#151515] p-5 sm:p-6"
        >
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold">{{ t('miles.history_title') }}</h2>
                <span class="text-xs text-[#858585]">{{
                    t('miles.no_expiry')
                }}</span>
            </div>
            <ul
                v-if="props.history.data.length"
                class="mt-4 divide-y divide-white/7"
            >
                <li
                    v-for="entry in props.history.data"
                    :key="entry.id"
                    class="flex items-center gap-3 py-3"
                >
                    <span
                        class="grid size-9 shrink-0 place-items-center rounded-xl"
                        :class="
                            entry.amount > 0
                                ? 'bg-[#02cd86]/10 text-[#02cd86]'
                                : 'bg-[#d9c48f]/10 text-[#d9c48f]'
                        "
                    >
                        <Sparkles class="size-4" aria-hidden="true" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">
                            {{ humanize(entry.reason) }}
                        </p>
                        <p class="mt-0.5 text-xs text-[#777]">
                            {{ formatDate(entry.createdAt) }}
                        </p>
                    </div>
                    <div class="text-end">
                        <p
                            class="text-sm font-semibold"
                            :class="
                                entry.amount > 0
                                    ? 'text-[#5eeeb5]'
                                    : 'text-[#d9c48f]'
                            "
                        >
                            {{ entry.amount > 0 ? '+' : '' }}{{ entry.amount }}
                        </p>
                        <p class="text-xs text-[#777]">
                            {{ entry.balanceAfter }}
                        </p>
                    </div>
                </li>
            </ul>
            <p v-else class="mt-4 text-sm text-[#858585]">
                {{ t('miles.history_empty') }}
            </p>
            <nav
                v-if="props.history.links.length > 3"
                class="mt-4 flex flex-wrap gap-2"
                :aria-label="t('miles.history_title')"
            >
                <Link
                    v-for="link in props.history.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    preserve-scroll
                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl px-3 text-sm transition-colors"
                    :class="
                        link.active
                            ? 'bg-[#02cd86] text-[#07130e]'
                            : link.url
                              ? 'bg-white/5 text-[#b5b5b5] hover:bg-white/10'
                              : 'pointer-events-none bg-white/[0.02] text-[#555]'
                    "
                >
                    {{ paginationLabel(link.label) }}
                </Link>
            </nav>
        </section>
    </main>
</template>
