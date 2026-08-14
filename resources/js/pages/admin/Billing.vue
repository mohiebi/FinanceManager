<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, ExternalLink } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { PaymentRecord, PaymentTone } from '@/types/billing';

type AdminPayment = PaymentRecord & {
    user: { id: number | null; name: string | null; email: string | null };
    from_address: string | null;
    from_address_shared_with: number;
    admin_note: string | null;
    attempts: number;
    updated_at: string;
};

type ProUser = {
    id: number;
    name: string;
    email: string;
    pro_until: string;
    last_reason: string | null;
};

defineProps<{
    counts: Record<string, number>;
    needsAttention: AdminPayment[];
    recent: AdminPayment[];
    proUsers: ProUser[];
    status: string | null;
}>();

const { t } = useI18n();

const noteFor = ref<Record<string, string>>({});
const grantMonths = ref<Record<number, number>>({});
const grantNote = ref<Record<number, string>>({});
const busy = ref<string | null>(null);

function act(
    url: string,
    key: string,
    data: Record<string, string | number>,
): void {
    busy.value = key;

    router.post(url, data, {
        preserveScroll: true,
        onFinish: () => {
            busy.value = null;
        },
    });
}

const toneClasses: Record<PaymentTone, string> = {
    positive: 'bg-[#1f2e22] text-[#7BD88F] ring-[#7BD88F]/20',
    pending: 'bg-[#2b2618] text-[#E0B341] ring-[#E0B341]/20',
    negative: 'bg-[#2c1b1b] text-[#E94E50] ring-[#E94E50]/20',
    neutral: 'bg-white/5 text-[#989898] ring-white/10',
};

function shortHash(value: string | null): string {
    return value ? `${value.slice(0, 10)}…${value.slice(-8)}` : '';
}
</script>

<template>
    <Head :title="t('billing.admin.title')" />

    <div class="space-y-6 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-medium text-white">
                {{ t('billing.admin.title') }}
            </h1>
            <p class="mt-1 text-sm text-[#989898]">
                {{ t('billing.admin.description') }}
            </p>
        </div>

        <p
            v-if="status"
            class="rounded-xl bg-[#1f2e22] px-4 py-3 text-sm text-[#7BD88F] ring-1 ring-[#7BD88F]/20"
        >
            {{ status }}
        </p>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div
                v-for="key in [
                    'pro_users',
                    'pending',
                    'submitted',
                    'confirmed',
                ]"
                :key="key"
                class="rounded-2xl bg-[#1a1a1a] p-4 ring-1 ring-white/10"
            >
                <p class="text-2xl font-medium text-white">
                    {{ counts[key] ?? 0 }}
                </p>
                <p class="mt-1 text-xs text-[#989898]">
                    {{
                        key === 'pro_users'
                            ? t('billing.admin.pro_users')
                            : t(`billing.statuses.${key}`)
                    }}
                </p>
            </div>
        </div>

        <!-- The queue that matters: everything automatic verification refused to
             guess at, where money probably arrived. -->
        <section class="rounded-[22px] bg-[#1a1a1a] p-6 ring-1 ring-white/10">
            <h2 class="mb-4 flex items-center gap-2 text-[17px] text-white">
                <AlertTriangle class="size-4 text-[#E0B341]" />
                {{ t('billing.admin.needs_attention') }}
            </h2>

            <p
                v-if="needsAttention.length === 0"
                class="text-sm text-[#989898]"
            >
                {{ t('billing.admin.needs_attention_empty') }}
            </p>

            <ul v-else class="space-y-4">
                <li
                    v-for="payment in needsAttention"
                    :key="payment.id"
                    class="rounded-2xl bg-black/30 p-4 ring-1 ring-white/5"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="min-w-0">
                            <p class="text-sm text-white">
                                {{ payment.user.email }}
                                <span class="text-[#6f6f6f]">
                                    · {{ payment.plan_label }} ·
                                    <span dir="ltr"
                                        >${{ payment.price_usd }}</span
                                    >
                                </span>
                            </p>
                            <p
                                class="mt-1 font-mono text-xs break-all text-[#989898] [unicode-bidi:isolate]"
                                dir="ltr"
                            >
                                {{ shortHash(payment.tx_hash) }}
                            </p>
                            <p
                                v-if="payment.failure_message"
                                class="mt-1 text-xs text-[#E0B341]"
                            >
                                {{ payment.failure_message }}
                            </p>
                            <p
                                v-if="payment.from_address_shared_with > 1"
                                class="mt-1 text-xs text-[#E94E50]"
                            >
                                {{
                                    t('billing.admin.shared_sender', {
                                        count:
                                            payment.from_address_shared_with -
                                            1,
                                    })
                                }}
                            </p>
                            <p class="mt-1 text-xs text-[#6f6f6f]">
                                {{
                                    t('billing.admin.attempts', {
                                        count: payment.attempts,
                                    })
                                }}
                                <template v-if="payment.received_amount">
                                    ·
                                    <span dir="ltr"
                                        >{{ payment.received_amount }}
                                        {{ payment.asset_symbol }}</span
                                    >
                                    /
                                    <span dir="ltr"
                                        >{{ payment.expected_amount }}
                                        {{ payment.asset_symbol }}</span
                                    >
                                </template>
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <a
                                v-if="payment.explorer_url"
                                :href="payment.explorer_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1 text-xs text-[#989898] hover:text-white"
                            >
                                {{ t('billing.history.view_tx') }}
                                <ExternalLink class="size-3" />
                            </a>
                            <span
                                class="rounded-full px-3 py-1 text-xs ring-1"
                                :class="toneClasses[payment.tone]"
                            >
                                {{ payment.status_label }}
                            </span>
                        </div>
                    </div>

                    <!-- Every decision here needs a written reason: an audit
                         trail nobody explained is barely better than none. -->
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <Input
                            v-model="noteFor[payment.id]"
                            class="min-w-[200px] flex-1"
                            :placeholder="t('billing.admin.note_placeholder')"
                        />
                        <Button
                            size="sm"
                            :disabled="busy === payment.id"
                            @click="
                                act(
                                    `/admin/billing/payments/${payment.id}/approve`,
                                    payment.id,
                                    { note: noteFor[payment.id] ?? '' },
                                )
                            "
                        >
                            {{ t('billing.admin.approve') }}
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            :disabled="busy === payment.id"
                            @click="
                                act(
                                    `/admin/billing/payments/${payment.id}/reject`,
                                    payment.id,
                                    { note: noteFor[payment.id] ?? '' },
                                )
                            "
                        >
                            {{ t('billing.admin.reject') }}
                        </Button>
                        <Button
                            v-if="payment.status === 'submitted'"
                            size="sm"
                            variant="ghost"
                            :disabled="busy === payment.id"
                            @click="
                                act(
                                    `/admin/billing/payments/${payment.id}/recheck`,
                                    payment.id,
                                    {},
                                )
                            "
                        >
                            {{ t('billing.admin.recheck') }}
                        </Button>
                    </div>
                </li>
            </ul>
        </section>

        <section class="rounded-[22px] bg-[#1a1a1a] p-6 ring-1 ring-white/10">
            <h2 class="mb-4 text-[17px] text-white">
                {{ t('billing.admin.pro_users') }}
            </h2>

            <p v-if="proUsers.length === 0" class="text-sm text-[#989898]">
                {{ t('billing.admin.pro_users_empty') }}
            </p>

            <ul v-else class="divide-y divide-white/5">
                <li
                    v-for="user in proUsers"
                    :key="user.id"
                    class="flex flex-wrap items-center justify-between gap-3 py-3"
                >
                    <div class="min-w-0">
                        <p class="text-sm text-white">{{ user.email }}</p>
                        <p class="text-xs text-[#6f6f6f]">
                            {{
                                t('billing.admin.until', {
                                    date: user.pro_until.slice(0, 10),
                                })
                            }}
                            <template v-if="user.last_reason">
                                · {{ user.last_reason }}
                            </template>
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Input
                            v-model.number="grantMonths[user.id]"
                            type="number"
                            min="1"
                            max="120"
                            class="w-20"
                            :placeholder="t('billing.admin.months')"
                        />
                        <Input
                            v-model="grantNote[user.id]"
                            class="min-w-[160px]"
                            :placeholder="t('billing.admin.note_placeholder')"
                        />
                        <Button
                            size="sm"
                            variant="ghost"
                            :disabled="busy === `u${user.id}`"
                            @click="
                                act(
                                    `/admin/billing/users/${user.id}/grant`,
                                    `u${user.id}`,
                                    {
                                        months: grantMonths[user.id] ?? 1,
                                        note: grantNote[user.id] ?? '',
                                    },
                                )
                            "
                        >
                            {{ t('billing.admin.grant') }}
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            :disabled="busy === `u${user.id}`"
                            @click="
                                act(
                                    `/admin/billing/users/${user.id}/revoke`,
                                    `u${user.id}`,
                                    { note: grantNote[user.id] ?? '' },
                                )
                            "
                        >
                            {{ t('billing.admin.revoke') }}
                        </Button>
                    </div>
                </li>
            </ul>
        </section>

        <section class="rounded-[22px] bg-[#1a1a1a] p-6 ring-1 ring-white/10">
            <h2 class="mb-4 text-[17px] text-white">
                {{ t('billing.admin.recent') }}
            </h2>

            <ul class="divide-y divide-white/5">
                <li
                    v-for="payment in recent"
                    :key="payment.id"
                    class="flex flex-wrap items-center justify-between gap-3 py-3"
                >
                    <div class="min-w-0">
                        <p class="text-sm text-white">
                            {{ payment.user.email }}
                            <span class="text-[#6f6f6f]"
                                >· {{ payment.plan_label }}</span
                            >
                        </p>
                        <p class="text-xs text-[#6f6f6f]" dir="ltr">
                            {{ payment.expected_amount }}
                            {{ payment.asset_symbol }}
                        </p>
                    </div>
                    <span
                        class="rounded-full px-3 py-1 text-xs ring-1"
                        :class="toneClasses[payment.tone]"
                    >
                        {{ payment.status_label }}
                    </span>
                </li>
            </ul>
        </section>
    </div>
</template>
