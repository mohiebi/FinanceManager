<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRelativeTime } from '@/composables/useRelativeTime';
import { edit, read as readNotification, readAll as readAllNotifications } from '@/routes/notifications';

type NotificationItem = {
    id: string;
    data: { type: string; title: string; body: string };
    read_at: string | null;
    created_at: string;
};

const props = defineProps<{
    notifications: NotificationItem[];
}>();

const { t } = useI18n();
const { formatRelativeTime } = useRelativeTime();

const unreadCount = computed(() => props.notifications.filter((n) => !n.read_at).length);
const markingAll = ref(false);

const markRead = (id: string) => {
    router.patch(readNotification.url(id), {}, { preserveScroll: true, preserveState: true });
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

    <div class="space-y-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-lg font-medium text-white">{{ t('notifications.page_title') }}</h1>
                <p class="mt-1 text-sm text-[#989898]">{{ t('notifications.page_description') }}</p>
            </div>
            <button
                v-if="unreadCount > 0"
                type="button"
                :disabled="markingAll"
                class="shrink-0 cursor-pointer rounded-full bg-white/8 px-3 py-1.5 text-xs font-medium text-[#02cd86] ring-1 ring-white/15 transition-colors hover:bg-white/15 disabled:cursor-not-allowed disabled:opacity-50"
                @click="markAllRead"
            >
                {{ t('notifications.mark_all_read') }}
            </button>
        </div>

        <div
            v-if="notifications.length === 0"
            class="flex flex-col items-center gap-2 rounded-2xl border border-white/10 px-6 py-16 text-center"
        >
            <Bell class="size-8 text-[#6C4EE9]" />
            <p class="text-sm font-medium text-white/70">{{ t('notifications.empty') }}</p>
            <p class="text-xs text-[#989898]">{{ t('notifications.empty_description') }}</p>
        </div>

        <div v-else class="overflow-hidden rounded-2xl border border-white/10">
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
                        <p class="text-sm font-medium text-white">{{ notification.data.title }}</p>
                        <p class="mt-0.5 text-xs text-[#989898]">{{ notification.data.body }}</p>
                    </div>
                </div>
                <span class="shrink-0 text-[11px] whitespace-nowrap text-[#6b6b6b]">
                    {{ formatRelativeTime(notification.created_at) }}
                </span>
            </button>
        </div>
    </div>
</template>
