<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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
import type { NotificationItem } from '@/types/notifications';

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
    if (savingBillAdvanceReminder.value || !props.billAdvanceReminder.available) {
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

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Notifications', href: edit() }],
    },
});
</script>

<template>
    <Head :title="t('notifications.page_title')" />

    <div class="flex flex-col gap-[18px]">
        <SettingsSection
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
            :title="t('notifications.page_title')"
            :description="t('notifications.page_description')"
        >
            <template v-if="unreadCount > 0" #footer>
                <button
                    type="button"
                    :disabled="markingAll"
                    class="cursor-pointer rounded-full bg-white/8 px-3 py-1.5 text-xs font-medium text-[#02cd86] ring-1 ring-white/15 transition-colors duration-200 hover:bg-white/15 focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    @click="markAllRead"
                >
                    {{ t('notifications.mark_all_read') }}
                </button>
            </template>

            <div
                v-if="notifications.length === 0"
                class="flex flex-col items-center gap-2 rounded-2xl border border-white/10 px-6 py-16 text-center"
            >
                <Bell class="size-8 text-[#6C4EE9]" />
                <p class="text-sm font-medium text-white/70">
                    {{ t('notifications.empty') }}
                </p>
                <p class="text-xs text-[#989898]">
                    {{ t('notifications.empty_description') }}
                </p>
            </div>

            <div
                v-else
                class="overflow-hidden rounded-2xl border border-white/10"
            >
                <button
                    v-for="notification in notifications"
                    :key="notification.id"
                    type="button"
                    class="flex w-full cursor-pointer items-start justify-between gap-3 border-b border-white/5 px-4 py-3.5 text-left transition-colors last:border-0 hover:bg-white/5"
                    :class="notification.read_at ? 'opacity-60' : ''"
                    @click="!notification.read_at && markRead(notification.id)"
                >
                    <div class="flex items-start gap-3">
                        <span
                            v-if="!notification.read_at"
                            class="mt-1.5 size-1.5 shrink-0 rounded-full bg-[#02cd86]"
                            :aria-label="t('notifications.unread')"
                        />
                        <span v-else class="mt-1.5 size-1.5 shrink-0" />
                        <div>
                            <p class="text-sm font-medium text-white">
                                {{ notification.data.title }}
                            </p>
                            <p class="mt-0.5 text-xs text-[#989898]">
                                {{ notification.data.body }}
                            </p>
                        </div>
                    </div>
                    <span
                        class="shrink-0 text-[11px] whitespace-nowrap text-[#6b6b6b]"
                    >
                        {{ formatRelativeTime(notification.created_at) }}
                    </span>
                </button>
            </div>
        </SettingsSection>
    </div>
</template>
