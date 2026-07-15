<script setup lang="ts">
import {
    Bot,
    CheckCircle2,
    KeyRound,
    Landmark,
    Receipt,
    ReceiptText,
    X,
} from 'lucide-vue-next';
import { onMounted, onUnmounted } from 'vue';
import {
    formatAdminDate,
    formatAdminNumber,
    isRecentlyOnline,
    relativeActivity,
} from '@/lib/adminFormat';
import type { AdminUser } from '@/types';

const props = defineProps<{
    user: AdminUser | null;
}>();

const emit = defineEmits<{ close: [] }>();

const localeNames: Record<string, string> = {
    en: 'English',
    fa: 'Persian',
    de: 'German',
};

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && props.user) {
        emit('close');
    }
}

onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150"
            leave-to-class="opacity-0"
        >
            <div
                v-if="user"
                class="fixed inset-0 z-50 bg-black/60"
                aria-hidden="true"
                @click="emit('close')"
            />
        </Transition>
        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="translate-x-full"
            leave-active-class="transition duration-200"
            leave-to-class="translate-x-full"
        >
            <aside
                v-if="user"
                class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col overflow-y-auto bg-[#161616] p-6 text-white ring-1 ring-white/10"
                role="dialog"
                aria-modal="true"
                :aria-label="`Customer details for ${user.name}`"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ user.name }}</h2>
                        <p class="mt-0.5 text-sm text-[#989898]">
                            {{ user.email }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl border border-white/10 text-[#989898] transition hover:border-white/20 hover:text-white"
                        aria-label="Close customer details"
                        @click="emit('close')"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <div class="mt-5 flex flex-wrap gap-1.5">
                    <span
                        class="rounded-lg px-2 py-1 text-xs"
                        :class="
                            isRecentlyOnline(user.last_active_at)
                                ? 'bg-[#0d2e22] text-[#7ee8c4]'
                                : 'bg-white/5 text-[#b3b3b3]'
                        "
                        >{{ relativeActivity(user.last_active_at) }}</span
                    >
                    <span
                        class="rounded-lg px-2 py-1 text-xs"
                        :class="
                            user.is_verified
                                ? 'bg-[#0d2e22] text-[#7ee8c4]'
                                : 'bg-[#2f1717] text-[#ffb4b4]'
                        "
                        >{{
                            user.is_verified ? 'Verified' : 'Unverified'
                        }}</span
                    >
                    <span
                        class="rounded-lg bg-white/5 px-2 py-1 text-xs text-[#989898]"
                    >
                        {{
                            user.profile_complete
                                ? 'Profile complete'
                                : 'Profile incomplete'
                        }}
                    </span>
                    <span
                        class="rounded-lg px-2 py-1 text-xs"
                        :class="
                            user.telegram_connected
                                ? 'bg-[#201d31] text-[#b8aaff]'
                                : 'bg-white/5 text-[#686868]'
                        "
                    >
                        <Bot class="mr-1 inline size-3.5 align-[-2px]" />
                        {{
                            user.telegram_connected
                                ? 'Telegram connected'
                                : 'No Telegram'
                        }}
                    </span>
                </div>

                <dl class="mt-6 space-y-4 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-[#989898]">Joined</dt>
                        <dd>{{ formatAdminDate(user.joined_at) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-[#989898]">Last active</dt>
                        <dd>
                            {{
                                user.last_active_at
                                    ? relativeActivity(user.last_active_at)
                                    : 'Never seen'
                            }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-[#989898]">Authentication</dt>
                        <dd class="flex items-center gap-1.5">
                            <KeyRound class="size-3.5 text-[#686868]" />
                            {{ user.auth_method }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-[#989898]">Language</dt>
                        <dd>{{ localeNames[user.locale] ?? user.locale }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-[#989898]">Signup source</dt>
                        <dd>{{ user.signup_source ?? 'Direct / unknown' }}</dd>
                    </div>
                </dl>

                <h3
                    class="mt-8 text-[11px] font-medium tracking-[0.12em] text-[#686868] uppercase"
                >
                    Product usage
                </h3>
                <div class="mt-3 grid grid-cols-3 gap-2">
                    <div class="rounded-xl bg-white/[0.04] p-3">
                        <ReceiptText class="size-4 text-[#02CD86]" />
                        <p class="mt-2 text-lg font-semibold tabular-nums">
                            {{ formatAdminNumber(user.transaction_count) }}
                        </p>
                        <p class="text-xs text-[#686868]">Transactions</p>
                    </div>
                    <div class="rounded-xl bg-white/[0.04] p-3">
                        <Landmark class="size-4 text-[#6C4EE9]" />
                        <p class="mt-2 text-lg font-semibold tabular-nums">
                            {{ formatAdminNumber(user.investment_count) }}
                        </p>
                        <p class="text-xs text-[#686868]">Investments</p>
                    </div>
                    <div class="rounded-xl bg-white/[0.04] p-3">
                        <Receipt class="size-4 text-[#947bff]" />
                        <p class="mt-2 text-lg font-semibold tabular-nums">
                            {{ formatAdminNumber(user.bill_count) }}
                        </p>
                        <p class="text-xs text-[#686868]">Bills</p>
                    </div>
                </div>

                <p class="mt-6 flex items-center gap-2 text-xs text-[#686868]">
                    <CheckCircle2 class="size-4 text-[#02CD86]" />
                    Read-only view. No account actions are available here.
                </p>
            </aside>
        </Transition>
    </Teleport>
</template>
