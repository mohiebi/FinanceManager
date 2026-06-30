<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell, BellRing } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useRelativeTime } from '@/composables/useRelativeTime';
import { edit as editNotifications, read as readNotification, readAll as readAllNotifications } from '@/routes/notifications';

const { t } = useI18n();
const page = usePage();
const { formatRelativeTime } = useRelativeTime();

const notifications = computed(() => page.props.notifications);
const unreadCount = computed(() => notifications.value?.unread_count ?? 0);
const recent = computed(() => notifications.value?.recent ?? []);

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
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="group relative grid size-9 cursor-pointer place-items-center rounded-md bg-[#2d2d2d] text-white transition-colors duration-150 hover:bg-[#02cd86] hover:text-[#1a1a1a]"
            >
                <BellRing v-if="unreadCount > 0" class="size-[18px]" />
                <Bell v-else class="size-[18px]" />
                <span
                    v-if="unreadCount > 0"
                    class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-[#E94E50] px-1 text-[10px] font-semibold text-white"
                >
                    {{ unreadCount > 9 ? '9+' : unreadCount }}
                </span>
                <span class="sr-only">{{ t('navigation.notifications') }}</span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-80 bg-[#1a1a1a] p-0 text-white ring-1 ring-white/10">
            <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                <span class="text-sm font-medium text-white">{{ t('navigation.notifications') }}</span>
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    :disabled="markingAll"
                    class="cursor-pointer text-xs text-[#02cd86] transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                    @click="markAllRead"
                >
                    {{ t('notifications.mark_all_read') }}
                </button>
            </div>

            <div class="max-h-80 overflow-y-auto">
                <div
                    v-if="recent.length === 0"
                    class="flex flex-col items-center gap-1 px-4 py-8 text-center"
                >
                    <p class="text-sm font-medium text-white/70">{{ t('notifications.empty') }}</p>
                    <p class="text-xs text-[#989898]">{{ t('notifications.empty_description') }}</p>
                </div>

                <button
                    v-for="notification in recent"
                    :key="notification.id"
                    type="button"
                    class="flex w-full cursor-pointer flex-col items-start gap-0.5 border-b border-white/5 px-4 py-3 text-left transition-colors last:border-0 hover:bg-white/5"
                    :class="notification.read_at ? 'opacity-60' : ''"
                    @click="!notification.read_at && markRead(notification.id)"
                >
                    <div class="flex w-full items-center gap-2">
                        <span
                            v-if="!notification.read_at"
                            class="size-1.5 shrink-0 rounded-full bg-[#02cd86]"
                        />
                        <span class="truncate text-sm font-medium text-white">
                            {{ notification.data.title }}
                        </span>
                    </div>
                    <p class="text-xs text-[#989898]">{{ notification.data.body }}</p>
                    <p class="text-[10px] text-[#6b6b6b]">{{ formatRelativeTime(notification.created_at) }}</p>
                </button>
            </div>

            <Link
                :href="editNotifications()"
                class="block border-t border-white/10 px-4 py-2.5 text-center text-xs font-medium text-[#02cd86] transition-colors hover:text-white"
            >
                {{ t('finance.actions.see_all') }}
            </Link>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
