<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BadgeCheck,
    BellOff,
    CalendarClock,
    CalendarX,
    CheckCheck,
    CreditCard,
    Flame,
    Inbox,
    SlidersHorizontal,
    Trophy,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type { Component } from 'vue';
import { useI18n } from 'vue-i18n';
import SettingsRow from '@/components/settings/SettingsRow.vue';
import SettingsSection from '@/components/settings/SettingsSection.vue';
import { Switch } from '@/components/ui/switch';
import { useRelativeTime } from '@/composables/useRelativeTime';
import {
    edit,
    preferences as notificationPreferences,
    read as readNotification,
    readAll as readAllNotifications,
} from '@/routes/notifications';
import type {
    NotificationDataType,
    NotificationItem,
} from '@/types/notifications';

const props = defineProps<{
    notifications: NotificationItem[];
    streakNudge: {
        enabled: boolean;
        available: boolean;
        telegramLinked: boolean;
        hour: number;
    };
    billAdvanceReminder: {
        enabled: boolean;
        available: boolean;
        days: number;
    };
}>();

const { t } = useI18n();
const { formatRelativeTime } = useRelativeTime();
const savingNudge = ref(false);
const streakNudgeEnabled = ref(props.streakNudge.enabled);
const savingBillAdvanceReminder = ref(false);
const billAdvanceReminderEnabled = ref(props.billAdvanceReminder.enabled);

watch(
    () => props.streakNudge.enabled,
    (enabled) => {
        streakNudgeEnabled.value = enabled;
    },
);

watch(
    () => props.billAdvanceReminder.enabled,
    (enabled) => {
        billAdvanceReminderEnabled.value = enabled;
    },
);

const { start: startTelegramPolling, stop: stopTelegramPolling } = usePoll(
    3_000,
    {
        only: ['streakNudge'],
    },
    { autoStart: false },
);

watch(
    () => props.streakNudge.telegramLinked,
    (telegramLinked) => {
        if (telegramLinked) {
            stopTelegramPolling();

            return;
        }

        startTelegramPolling();
    },
    { immediate: true },
);

const setStreakNudge = (enabled: boolean) => {
    if (savingNudge.value || !props.streakNudge.available) {
        return;
    }

    streakNudgeEnabled.value = enabled;
    savingNudge.value = true;
    router.patch(
        notificationPreferences().url,
        { streak_nudge_enabled: enabled },
        {
            preserveScroll: true,
            onFinish: () => {
                savingNudge.value = false;
                streakNudgeEnabled.value = props.streakNudge.enabled;
            },
        },
    );
};

const setBillAdvanceReminder = (enabled: boolean) => {
    if (
        savingBillAdvanceReminder.value ||
        !props.billAdvanceReminder.available
    ) {
        return;
    }

    billAdvanceReminderEnabled.value = enabled;
    savingBillAdvanceReminder.value = true;
    router.patch(
        notificationPreferences().url,
        { bill_advance_reminder_enabled: enabled },
        {
            preserveScroll: true,
            onFinish: () => {
                savingBillAdvanceReminder.value = false;
                billAdvanceReminderEnabled.value =
                    props.billAdvanceReminder.enabled;
            },
        },
    );
};

const unreadCount = computed(
    () => props.notifications.filter((n) => !n.read_at).length,
);
const markingAll = ref(false);

const markRead = (id: string) => {
    router.patch(
        readNotification.url(id),
        {},
        { preserveScroll: true, preserveState: true },
    );
};

const markAllRead = () => {
    markingAll.value = true;
    router.patch(
        readAllNotifications.url(),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                markingAll.value = false;
            },
        },
    );
};

/**
 * An icon and a tone per kind of notification.
 *
 * Every row used to look identical — one green dot for unread and nothing else
 * — so an inbox of a hundred was a hundred lines of grey text to read in full.
 * A bill falling due today and a milestone are not the same news, and the
 * difference is worth seeing before the sentence is read.
 */
const typeStyles: Record<
    NotificationDataType,
    { icon: Component; tone: string }
> = {
    bill_due_today: {
        icon: CalendarX,
        tone: 'bg-[#E94E50]/10 text-[#E94E50]',
    },
    bill_due_tomorrow: {
        icon: CalendarClock,
        tone: 'bg-[#E0B341]/10 text-[#E0B341]',
    },
    streak_open: { icon: Flame, tone: 'bg-[#E0B341]/10 text-[#E0B341]' },
    milestone: { icon: Trophy, tone: 'bg-[#6C4EE9]/15 text-[#a89bf3]' },
    payment_needs_review: {
        icon: AlertTriangle,
        tone: 'bg-[#E0B341]/10 text-[#E0B341]',
    },
    subscription_activated: {
        icon: BadgeCheck,
        tone: 'bg-[#02CD86]/10 text-[#02CD86]',
    },
    subscription_expiring: {
        icon: CalendarClock,
        tone: 'bg-[#E0B341]/10 text-[#E0B341]',
    },
    subscription_expired: {
        icon: CalendarX,
        tone: 'bg-white/5 text-[#989898]',
    },
    subscription_payment_failed: {
        icon: CreditCard,
        tone: 'bg-[#E94E50]/10 text-[#E94E50]',
    },
};

// A type this build has not heard of still renders a row rather than an empty
// square: the server can start sending a new kind before the client ships.
const fallbackStyle = { icon: Inbox, tone: 'bg-white/5 text-[#989898]' };

function styleFor(type: NotificationDataType) {
    return typeStyles[type] ?? fallbackStyle;
}

type Filter = 'all' | 'unread';

const filter = ref<Filter>('all');

const filtered = computed<NotificationItem[]>(() =>
    filter.value === 'unread'
        ? props.notifications.filter((notification) => !notification.read_at)
        : props.notifications,
);

type Bucket = 'today' | 'yesterday' | 'earlier';

/**
 * Which day a notification landed on, relative to now.
 *
 * Relative rather than formatted, deliberately: a heading built from a date
 * would have to be rendered in the reader's calendar, and "Today" is both
 * shorter and correct in every one of them.
 */
function bucketFor(iso: string): Bucket {
    const startOfToday = new Date();
    startOfToday.setHours(0, 0, 0, 0);

    const at = new Date(iso).getTime();

    if (at >= startOfToday.getTime()) {
        return 'today';
    }

    return at >= startOfToday.getTime() - 86_400_000 ? 'yesterday' : 'earlier';
}

/**
 * The visible list, cut into day groups.
 *
 * Empty groups are dropped rather than rendered as a heading with nothing under
 * it, so switching to the unread filter cannot leave a stray "Yesterday".
 */
const groups = computed<{ key: Bucket; items: NotificationItem[] }[]>(() => {
    const buckets: Record<Bucket, NotificationItem[]> = {
        today: [],
        yesterday: [],
        earlier: [],
    };

    for (const notification of filtered.value) {
        buckets[bucketFor(notification.created_at)].push(notification);
    }

    return (['today', 'yesterday', 'earlier'] as const)
        .map((key) => ({ key, items: buckets[key] }))
        .filter((group) => group.items.length > 0);
});

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Notifications', href: edit() }],
    },
});
</script>

<template>
    <Head :title="t('notifications.page_title')" />

    <div class="flex flex-col gap-[18px]">
        <!-- Preferences first. Deciding what CashPilot may send is the short
             block and the reason most people open the page; the inbox is the
             long one, so it sits underneath rather than pushing the switches
             off the first screen. -->
        <SettingsSection
            :icon="SlidersHorizontal"
            :title="t('notifications.preferences_heading')"
            :description="t('notifications.preferences_description')"
        >
            <SettingsRow
                :label="t('notifications.streak_nudge_label')"
                :help="
                    props.streakNudge.available
                        ? t('notifications.streak_nudge_hint', {
                              hour: props.streakNudge.hour,
                          })
                        : t('notifications.streak_nudge_unavailable')
                "
            >
                <Switch
                    :checked="streakNudgeEnabled"
                    :disabled="!props.streakNudge.available || savingNudge"
                    @update:checked="setStreakNudge($event === true)"
                />
            </SettingsRow>

            <SettingsRow
                :label="t('notifications.bill_advance_reminder_label')"
                :help="
                    props.billAdvanceReminder.available
                        ? t('notifications.bill_advance_reminder_hint', {
                              days: props.billAdvanceReminder.days,
                          })
                        : t('notifications.bill_advance_reminder_unavailable')
                "
                last
            >
                <Switch
                    :checked="billAdvanceReminderEnabled"
                    :disabled="
                        !props.billAdvanceReminder.available ||
                        savingBillAdvanceReminder
                    "
                    @update:checked="setBillAdvanceReminder($event === true)"
                />
            </SettingsRow>
        </SettingsSection>

        <SettingsSection
            :icon="Inbox"
            :title="t('notifications.page_title')"
            :description="t('notifications.page_description')"
        >
            <template v-if="notifications.length > 0" #actions>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <!-- Segmented rather than a checkbox: with a hundred rows
                         loaded, "show me only what I have not read" is the
                         question being asked, and it deserves a control that
                         reads as a choice between two lists. -->
                    <div
                        class="flex items-center gap-0.5 rounded-full bg-white/5 p-0.5 ring-1 ring-white/10"
                        role="group"
                        :aria-label="t('notifications.page_title')"
                    >
                        <button
                            v-for="option in ['all', 'unread'] as const"
                            :key="option"
                            type="button"
                            :aria-pressed="filter === option"
                            class="inline-flex min-h-9 cursor-pointer items-center gap-1.5 rounded-full px-3.5 text-xs font-medium transition-colors duration-200 focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none"
                            :class="
                                filter === option
                                    ? 'bg-[#02CD86]/12 text-[#02CD86]'
                                    : 'text-[#989898] hover:text-white'
                            "
                            @click="filter = option"
                        >
                            {{ t(`notifications.filter.${option}`) }}
                            <span
                                v-if="option === 'unread' && unreadCount > 0"
                                class="rounded-full bg-[#02CD86] px-1.5 text-[10px] font-semibold text-[#101010]"
                            >
                                {{ unreadCount }}
                            </span>
                        </button>
                    </div>

                    <button
                        v-if="unreadCount > 0"
                        type="button"
                        :disabled="markingAll"
                        class="inline-flex min-h-9 cursor-pointer items-center gap-1.5 rounded-full bg-white/8 px-3.5 text-xs font-medium text-[#02cd86] ring-1 ring-white/15 transition-colors duration-200 hover:bg-white/15 focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        @click="markAllRead"
                    >
                        <CheckCheck class="size-3.5" aria-hidden="true" />
                        {{ t('notifications.mark_all_read') }}
                    </button>
                </div>
            </template>

            <div
                v-if="filtered.length === 0"
                class="flex flex-col items-center gap-2 rounded-2xl border border-white/10 px-6 py-16 text-center"
            >
                <span
                    class="mb-1 flex size-12 items-center justify-center rounded-2xl bg-[#02CD86]/10 text-[#02CD86]"
                >
                    <component
                        :is="filter === 'unread' ? CheckCheck : BellOff"
                        class="size-6"
                        aria-hidden="true"
                    />
                </span>
                <p class="text-sm font-medium text-white">
                    {{
                        filter === 'unread'
                            ? t('notifications.empty_unread')
                            : t('notifications.empty')
                    }}
                </p>
                <p class="max-w-[46ch] text-xs text-[#989898]">
                    {{
                        filter === 'unread'
                            ? t('notifications.empty_unread_description')
                            : t('notifications.empty_description')
                    }}
                </p>
            </div>

            <div v-else class="space-y-5">
                <section v-for="group in groups" :key="group.key">
                    <h3
                        class="px-1 pb-2 text-[11px] font-medium tracking-[0.2em] text-[#6f6f6f] uppercase"
                    >
                        {{ t(`notifications.groups.${group.key}`) }}
                    </h3>

                    <ul
                        class="overflow-hidden rounded-2xl border border-white/10"
                    >
                        <li
                            v-for="notification in group.items"
                            :key="notification.id"
                            class="border-b border-white/5 last:border-0"
                        >
                            <!-- Unread rows act; read rows do not. The list used
                                 to be buttons all the way down, half of which
                                 did nothing when pressed while still announcing
                                 themselves as buttons and lighting up on
                                 hover. -->
                            <component
                                :is="notification.read_at ? 'div' : 'button'"
                                :type="
                                    notification.read_at ? undefined : 'button'
                                "
                                :aria-label="
                                    notification.read_at
                                        ? undefined
                                        : t('notifications.mark_read')
                                "
                                class="flex w-full items-start gap-3 px-4 py-3.5 text-start"
                                :class="
                                    notification.read_at
                                        ? ''
                                        : 'cursor-pointer transition-colors duration-200 hover:bg-white/5 focus-visible:bg-white/5 focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:-outline-offset-2 focus-visible:outline-none'
                                "
                                @click="
                                    notification.read_at ||
                                    markRead(notification.id)
                                "
                            >
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-xl"
                                    :class="
                                        notification.read_at
                                            ? 'bg-white/5 text-[#6f6f6f]'
                                            : styleFor(notification.data.type)
                                                  .tone
                                    "
                                >
                                    <component
                                        :is="
                                            styleFor(notification.data.type)
                                                .icon
                                        "
                                        class="size-[18px]"
                                        aria-hidden="true"
                                    />
                                </span>

                                <span class="min-w-0 flex-1">
                                    <!-- Only the title dims once read. Fading
                                         the whole row took the body and the
                                         timestamp under 4.5:1, which made an
                                         old notification one you cannot go back
                                         and read. -->
                                    <span
                                        class="block text-sm font-medium"
                                        :class="
                                            notification.read_at
                                                ? 'text-[#c8c8c8]'
                                                : 'text-white'
                                        "
                                    >
                                        {{ notification.data.title }}
                                    </span>
                                    <span
                                        class="mt-0.5 block text-xs text-[#989898]"
                                    >
                                        {{ notification.data.body }}
                                    </span>
                                </span>

                                <span
                                    class="flex shrink-0 items-center gap-2 text-[11px] whitespace-nowrap text-[#8b8b8b]"
                                >
                                    {{
                                        formatRelativeTime(
                                            notification.created_at,
                                        )
                                    }}
                                    <!-- Real text behind the dot, not an
                                         aria-label on a bare span — that is
                                         ignored on an element with no role. -->
                                    <span
                                        v-if="!notification.read_at"
                                        class="size-1.5 rounded-full bg-[#02cd86]"
                                    >
                                        <span class="sr-only">{{
                                            t('notifications.unread')
                                        }}</span>
                                    </span>
                                </span>
                            </component>
                        </li>
                    </ul>
                </section>
            </div>
        </SettingsSection>
    </div>
</template>
