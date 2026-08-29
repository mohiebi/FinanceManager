<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Clock3,
    ExternalLink,
    ShieldAlert,
    Ticket,
    WalletCards,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy as destroyCoupon,
    disable as disableCoupon,
    enable as enableCoupon,
    store as storeCoupon,
} from '@/routes/admin/coupons';
import type {
    AdminCoupon,
    CouponKindKey,
    PaymentRecord,
    PaymentTone,
} from '@/types/billing';

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

type PoolHealth = {
    network: string;
    network_label: string;
    available: number;
    assigned: number;
    retired: number;
    quarantined: number;
    warning: boolean;
    checkout_available: boolean;
};

type DepositRow = {
    id: string;
    network: string;
    network_label: string;
    address: string;
    derivation_index: number;
    status: string;
    payment_id: string | null;
    user_email: string | null;
    asset: string | null;
    asset_symbol: string | null;
    amount: string | null;
    screening_risk: string | null;
    block_timestamp: string | null;
    quarantine_reason: string | null;
    quarantined_at: string | null;
    swept_at: string | null;
};

type SignerHealth = {
    ok: boolean;
    locked: boolean;
    keyVersion?: string;
    unreachable?: boolean;
    gasWallet?: {
        address: string;
        balanceWei: string;
        spentHourlyWei: string;
        spentDailyWei: string;
    } | null;
};
type RiskCase = {
    id: string;
    payment_id: string;
    user_email: string;
    source_address: string;
    network: string;
    status: string;
    review_expires_at: string;
    settlement_status: string | null;
    authorization_note: string | null;
};
type SettlementRow = {
    id: string;
    operation_id: string;
    payment_id: string;
    user_email: string;
    network: string;
    asset: string;
    status: string;
    transaction_hashes: Record<string, string>;
    remaining_token_balance: string | null;
    remaining_eth_wei: string | null;
    failure_code: string | null;
    failure_message: string | null;
    updated_at: string;
};
type RiskEntry = {
    id: number;
    network: string;
    address: string;
    source: string;
    reason: string;
    active: boolean;
    admin_email: string;
    created_at: string;
};

defineProps<{
    counts: Record<string, number>;
    poolHealth: PoolHealth[];
    signerHealth: SignerHealth;
    riskCases: RiskCase[];
    settlements: SettlementRow[];
    riskEntries: RiskEntry[];
    needsAttention: AdminPayment[];
    delayedScreening: AdminPayment[];
    quarantined: DepositRow[];
    completedSweeps: DepositRow[];
    recent: AdminPayment[];
    proUsers: ProUser[];
    coupons: AdminCoupon[];
    status: string | null;
}>();

const { t } = useI18n();
const page = usePage();

/**
 * The create-coupon form.
 *
 * This page has never rendered a form before, so it follows the settings-page
 * convention instead: a useForm, InputError for field errors, and the kind
 * field switching which amount input is shown.
 */
const couponForm = useForm({
    code: '',
    kind: 'percent' as CouponKindKey,
    // Empty rather than null throughout: the Input component takes no null, and
    // Laravel's ConvertEmptyStringsToNull turns a blank back into null before
    // validation sees it, so the optional limits arrive as the nulls they mean.
    percent_off: 50 as number | string,
    amount_off_usd: '' as number | string,
    user_email: '',
    max_redemptions: '' as number | string,
    max_per_user: '' as number | string,
    valid_until: '',
    note: '',
});

function createCoupon(): void {
    couponForm.post(storeCoupon().url, {
        preserveScroll: true,
        onSuccess: () => couponForm.reset('code', 'user_email', 'note'),
    });
}

const couponToDelete = ref<AdminCoupon | null>(null);

function confirmDeleteCoupon(): void {
    const coupon = couponToDelete.value;

    if (coupon === null) {
        return;
    }

    couponToDelete.value = null;
    router.delete(destroyCoupon(coupon.id).url, { preserveScroll: true });
}

function couponStatus(coupon: AdminCoupon): string {
    if (coupon.disabled) {
        return t('billing.admin.coupons.status_disabled');
    }

    return coupon.expired
        ? t('billing.admin.coupons.status_expired')
        : t('billing.admin.coupons.status_active');
}

function couponUsage(coupon: AdminCoupon): string {
    return coupon.max_redemptions === null
        ? t('billing.admin.coupons.used_unlimited', {
              used: coupon.claimed_count,
          })
        : t('billing.admin.coupons.used', {
              used: coupon.claimed_count,
              total: coupon.max_redemptions,
          });
}

function couponValue(coupon: AdminCoupon): string {
    return coupon.kind === 'percent'
        ? `${coupon.percent_off}%`
        : `$${coupon.amount_off_usd}`;
}

const noteFor = ref<Record<string, string>>({});
const grantMonths = ref<Record<number, number>>({});
const grantNote = ref<Record<number, string>>({});
const busy = ref<string | null>(null);

function act(
    url: string,
    key: string,
    data: Record<string, string | number | null>,
): void {
    busy.value = key;

    router.post(url, data, {
        preserveScroll: true,
        onFinish: () => {
            busy.value = null;
        },
    });
}

const riskEntryForm = useForm({
    network: 'arbitrum',
    address: '',
    source: '',
    reason: '',
});

function saveRiskEntry(): void {
    riskEntryForm.post('/admin/billing/risk-addresses', {
        preserveScroll: true,
        onSuccess: () => riskEntryForm.reset('address', 'source', 'reason'),
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

        <p
            v-if="page.props.errors.note"
            class="rounded-xl bg-[#2c1b1b] px-4 py-3 text-sm text-[#E94E50] ring-1 ring-[#E94E50]/20"
        >
            {{ page.props.errors.note }}
        </p>

        <section class="rounded-[22px] bg-[#1a1a1a] p-6 ring-1 ring-white/10">
            <h2 class="mb-4 flex items-center gap-2 text-[17px] text-white">
                <WalletCards class="size-4 text-[#02CD86]" />
                {{ t('billing.admin.pool_health') }}
            </h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <div
                    v-for="pool in poolHealth"
                    :key="pool.network"
                    class="rounded-2xl bg-black/30 p-4 ring-1"
                    :class="pool.warning ? 'ring-[#E0B341]/30' : 'ring-white/5'"
                >
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-white">
                            {{ pool.network_label }}
                        </p>
                        <span
                            class="rounded-full px-2.5 py-1 text-xs ring-1"
                            :class="
                                pool.checkout_available
                                    ? 'bg-[#1f2e22] text-[#7BD88F] ring-[#7BD88F]/20'
                                    : 'bg-[#2c1b1b] text-[#E94E50] ring-[#E94E50]/20'
                            "
                        >
                            {{
                                pool.checkout_available
                                    ? t('billing.admin.checkout_on')
                                    : t('billing.admin.checkout_off')
                            }}
                        </span>
                    </div>
                    <p class="mt-3 text-2xl font-medium text-white">
                        {{ pool.available }}
                    </p>
                    <p class="text-xs text-[#989898]">
                        {{ t('billing.admin.addresses_available') }}
                    </p>
                    <p class="mt-2 text-xs text-[#6f6f6f]">
                        {{ t('billing.admin.pool_counts', pool) }}
                    </p>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
            <div
                v-for="key in [
                    'pro_users',
                    'pending',
                    'submitted',
                    'confirmed',
                    'quarantined',
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

        <section class="rounded-[22px] bg-[#1a1a1a] p-6 ring-1 ring-white/10">
            <h2 class="mb-4 flex items-center gap-2 text-[17px] text-white">
                <Clock3 class="size-4 text-[#E0B341]" />
                {{ t('billing.admin.delayed_screening') }}
            </h2>
            <p
                v-if="delayedScreening.length === 0"
                class="text-sm text-[#989898]"
            >
                {{ t('billing.admin.none') }}
            </p>
            <ul v-else class="space-y-3">
                <li
                    v-for="payment in delayedScreening"
                    :key="payment.id"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-black/30 p-4 ring-1 ring-white/5"
                >
                    <div>
                        <p class="text-sm text-white">
                            {{ payment.user.email }}
                        </p>
                        <p class="mt-1 text-xs text-[#989898]">
                            {{ payment.network_label }} ·
                            {{ payment.asset_symbol }} ·
                            {{ payment.failure_message }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
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
                            {{ t('billing.admin.recheck_screening') }}
                        </Button>
                        <!-- The way out when screening will never answer. The
                             funds are already at the deposit address, so this
                             refuses the payment and retires the address for
                             `billing:recover-address` rather than granting
                             anything on an unscreened transfer. -->
                        <Input
                            v-model="noteFor[payment.id]"
                            class="min-w-[200px]"
                            :placeholder="t('billing.admin.note_placeholder')"
                        />
                        <Button
                            size="sm"
                            variant="ghost"
                            :disabled="
                                busy === payment.id || !noteFor[payment.id]
                            "
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
                    </div>
                </li>
            </ul>
        </section>

        <section
            class="rounded-[22px] bg-[#1a1a1a] p-6 ring-1 ring-[#E94E50]/20"
        >
            <h2 class="mb-4 flex items-center gap-2 text-[17px] text-white">
                <ShieldAlert class="size-4 text-[#E94E50]" />
                {{ t('billing.admin.permanent_quarantine') }}
            </h2>
            <p v-if="quarantined.length === 0" class="text-sm text-[#989898]">
                {{ t('billing.admin.none') }}
            </p>
            <ul v-else class="space-y-3">
                <li
                    v-for="row in quarantined"
                    :key="row.id"
                    class="rounded-2xl bg-black/30 p-4 ring-1 ring-[#E94E50]/15"
                >
                    <p class="text-sm text-white">
                        {{ row.user_email }} · {{ row.network_label }} ·
                        {{ row.amount }} {{ row.asset_symbol }}
                    </p>
                    <p
                        class="mt-1 font-mono text-xs break-all text-[#989898]"
                        dir="ltr"
                    >
                        {{ row.address }}
                    </p>
                    <p class="mt-1 text-xs text-[#E94E50]">
                        {{ row.quarantine_reason }}
                    </p>
                    <p class="mt-2 text-xs text-[#6f6f6f]">
                        {{ t('billing.admin.quarantine_no_actions') }}
                    </p>
                </li>
            </ul>
        </section>

        <section class="rounded-[22px] bg-[#1a1a1a] p-6 ring-1 ring-white/10">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-[17px] text-white">Automated signer</h2>
                    <p class="mt-1 text-sm text-[#989898]">
                        Fixed settlement operations only; no raw signing API.
                    </p>
                </div>
                <span
                    class="rounded-full px-3 py-1 text-xs ring-1"
                    :class="
                        signerHealth.ok && !signerHealth.locked
                            ? 'bg-[#1f2e22] text-[#7BD88F] ring-[#7BD88F]/20'
                            : 'bg-[#2c1b1b] text-[#E94E50] ring-[#E94E50]/20'
                    "
                >
                    {{
                        signerHealth.ok
                            ? signerHealth.locked
                                ? 'Locked'
                                : 'Unlocked'
                            : 'Unavailable'
                    }}
                </span>
            </div>
            <p
                v-if="signerHealth.unreachable"
                class="mt-3 text-xs text-[#E94E50]"
            >
                {{ t('billing.admin.signer_unreachable') }}
            </p>
            <div
                v-if="signerHealth.gasWallet"
                class="mt-4 grid gap-2 text-xs text-[#989898] sm:grid-cols-3"
            >
                <p class="font-mono break-all" dir="ltr">
                    {{ signerHealth.gasWallet.address }}
                </p>
                <p>Balance: {{ signerHealth.gasWallet.balanceWei }} wei</p>
                <p>
                    Spent 1h / 24h:
                    {{ signerHealth.gasWallet.spentHourlyWei }} /
                    {{ signerHealth.gasWallet.spentDailyWei }} wei
                </p>
            </div>
        </section>

        <section class="rounded-[22px] bg-[#1a1a1a] p-6 ring-1 ring-white/10">
            <h2 class="mb-4 text-[17px] text-white">48-hour flagged cases</h2>
            <p v-if="riskCases.length === 0" class="text-sm text-[#989898]">
                {{ t('billing.admin.none') }}
            </p>
            <ul v-else class="space-y-3">
                <li
                    v-for="riskCase in riskCases"
                    :key="riskCase.id"
                    class="rounded-2xl bg-black/30 p-4 ring-1 ring-white/5"
                >
                    <div class="flex flex-wrap justify-between gap-3">
                        <div>
                            <p class="text-sm text-white">
                                {{ riskCase.user_email }} ·
                                {{ riskCase.network }} · {{ riskCase.status }}
                            </p>
                            <p
                                class="mt-1 font-mono text-xs break-all text-[#989898]"
                                dir="ltr"
                            >
                                {{ riskCase.source_address }}
                            </p>
                            <p class="mt-1 text-xs text-[#6f6f6f]">
                                Deadline:
                                {{
                                    new Date(
                                        riskCase.review_expires_at,
                                    ).toLocaleString()
                                }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="riskCase.status === 'pending'"
                                size="sm"
                                :disabled="
                                    busy === riskCase.id ||
                                    !noteFor[riskCase.id]
                                "
                                @click="
                                    act(
                                        `/admin/billing/risk-cases/${riskCase.id}/authorize`,
                                        riskCase.id,
                                        { note: noteFor[riskCase.id] ?? '' },
                                    )
                                "
                                >Authorize settlement</Button
                            >
                            <Button
                                v-if="riskCase.status === 'settled'"
                                size="sm"
                                :disabled="
                                    busy === riskCase.id ||
                                    !noteFor[riskCase.id]
                                "
                                @click="
                                    act(
                                        `/admin/billing/risk-cases/${riskCase.id}/grant`,
                                        riskCase.id,
                                        { note: noteFor[riskCase.id] ?? '' },
                                    )
                                "
                                >Grant Pro</Button
                            >
                        </div>
                    </div>
                    <Input
                        v-if="
                            riskCase.status === 'pending' ||
                            riskCase.status === 'settled'
                        "
                        v-model="noteFor[riskCase.id]"
                        class="mt-3"
                        placeholder="Required audit note"
                    />
                </li>
            </ul>
        </section>

        <section class="rounded-[22px] bg-[#1a1a1a] p-6 ring-1 ring-white/10">
            <h2 class="mb-4 text-[17px] text-white">Automatic settlements</h2>
            <p v-if="settlements.length === 0" class="text-sm text-[#989898]">
                {{ t('billing.admin.none') }}
            </p>
            <ul v-else class="divide-y divide-white/5">
                <li
                    v-for="settlement in settlements"
                    :key="settlement.id"
                    class="flex flex-wrap items-center justify-between gap-3 py-3"
                >
                    <div>
                        <p class="text-sm text-white">
                            {{ settlement.user_email }} ·
                            {{ settlement.network }} ·
                            {{ settlement.asset.toUpperCase() }} ·
                            {{ settlement.status }}
                        </p>
                        <p
                            v-if="settlement.failure_code"
                            class="mt-1 text-xs text-[#E94E50]"
                        >
                            {{ settlement.failure_code }}:
                            {{ settlement.failure_message }}
                        </p>
                        <p class="mt-1 text-xs text-[#6f6f6f]">
                            Token left:
                            {{ settlement.remaining_token_balance ?? '—' }} ·
                            ETH wei left:
                            {{ settlement.remaining_eth_wei ?? '—' }}
                        </p>
                    </div>
                    <Button
                        v-if="settlement.status === 'retryable_failure'"
                        size="sm"
                        variant="ghost"
                        @click="
                            act(
                                `/admin/billing/settlements/${settlement.id}/retry`,
                                settlement.id,
                                {},
                            )
                        "
                        >Retry</Button
                    >
                </li>
            </ul>
        </section>

        <section class="rounded-[22px] bg-[#1a1a1a] p-6 ring-1 ring-white/10">
            <h2 class="mb-4 text-[17px] text-white">Admin risk list</h2>
            <form
                class="grid gap-3 md:grid-cols-4"
                @submit.prevent="saveRiskEntry"
            >
                <select
                    v-model="riskEntryForm.network"
                    class="min-h-11 rounded-md border border-white/10 bg-black/30 px-3 text-sm text-white"
                >
                    <option value="ethereum">Ethereum</option>
                    <option value="arbitrum">Arbitrum</option>
                </select>
                <Input
                    v-model="riskEntryForm.address"
                    dir="ltr"
                    placeholder="0x address"
                />
                <Input v-model="riskEntryForm.source" placeholder="Source" />
                <Input v-model="riskEntryForm.reason" placeholder="Reason" />
                <Button type="submit" :disabled="riskEntryForm.processing"
                    >Save active entry</Button
                >
            </form>
            <ul class="mt-4 divide-y divide-white/5">
                <li
                    v-for="entry in riskEntries"
                    :key="entry.id"
                    class="flex flex-wrap items-center justify-between gap-3 py-3"
                >
                    <div>
                        <p class="font-mono text-xs text-white" dir="ltr">
                            {{ entry.address }}
                        </p>
                        <p class="mt-1 text-xs text-[#989898]">
                            {{ entry.network }} · {{ entry.source }} ·
                            {{ entry.reason }}
                        </p>
                    </div>
                    <Button
                        v-if="entry.active"
                        size="sm"
                        variant="ghost"
                        @click="
                            act(
                                `/admin/billing/risk-addresses/${entry.id}/deactivate`,
                                `risk-${entry.id}`,
                                {},
                            )
                        "
                        >Deactivate</Button
                    >
                    <span v-else class="text-xs text-[#6f6f6f]">Inactive</span>
                </li>
            </ul>
        </section>

        <!-- The queue that matters: everything automatic verification refused to
             guess at, where money probably arrived. -->
        <section class="rounded-[16px] bg-[#1a1a1a] p-6 ring-1 ring-white/10">
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

        <section class="rounded-[16px] bg-[#1a1a1a] p-6 ring-1 ring-white/10">
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

        <!-- Coupons -->
        <section class="rounded-[16px] bg-[#1a1a1a] p-6 ring-1 ring-white/10">
            <h2 class="mb-4 flex items-center gap-2 text-[17px] text-white">
                <Ticket class="size-4 text-[#02CD86]" />
                {{ t('billing.admin.coupons.heading') }}
            </h2>

            <form
                class="grid gap-3 rounded-2xl bg-black/30 p-4 ring-1 ring-white/5 sm:grid-cols-2 lg:grid-cols-4"
                @submit.prevent="createCoupon"
            >
                <div class="space-y-1.5">
                    <Label for="coupon_code">{{
                        t('billing.admin.coupons.code')
                    }}</Label>
                    <Input
                        id="coupon_code"
                        v-model="couponForm.code"
                        dir="ltr"
                        class="font-mono uppercase [unicode-bidi:isolate]"
                        :placeholder="
                            t('billing.admin.coupons.code_placeholder')
                        "
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="coupon_kind">{{
                        t('billing.admin.coupons.kind')
                    }}</Label>
                    <select
                        id="coupon_kind"
                        v-model="couponForm.kind"
                        class="finance-dialog-field finance-dialog-field-income"
                    >
                        <option value="percent">
                            {{ t('billing.coupon.kinds.percent') }}
                        </option>
                        <option value="fixed">
                            {{ t('billing.coupon.kinds.fixed') }}
                        </option>
                    </select>
                </div>

                <!-- The discriminator decides which amount is asked for, so a
                     coupon can never carry two contradictory values. -->
                <div v-if="couponForm.kind === 'percent'" class="space-y-1.5">
                    <Label for="coupon_percent">{{
                        t('billing.admin.coupons.percent')
                    }}</Label>
                    <Input
                        id="coupon_percent"
                        v-model.number="couponForm.percent_off"
                        type="number"
                        min="1"
                        max="100"
                    />
                </div>

                <div v-else class="space-y-1.5">
                    <Label for="coupon_amount">{{
                        t('billing.admin.coupons.amount')
                    }}</Label>
                    <Input
                        id="coupon_amount"
                        v-model="couponForm.amount_off_usd"
                        type="number"
                        min="0.01"
                        step="0.01"
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="coupon_user">{{
                        t('billing.admin.coupons.user_email')
                    }}</Label>
                    <Input
                        id="coupon_user"
                        v-model="couponForm.user_email"
                        type="email"
                        dir="ltr"
                        class="[unicode-bidi:isolate]"
                        :placeholder="
                            t('billing.admin.coupons.user_email_placeholder')
                        "
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="coupon_max">{{
                        t('billing.admin.coupons.max_redemptions')
                    }}</Label>
                    <Input
                        id="coupon_max"
                        v-model.number="couponForm.max_redemptions"
                        type="number"
                        min="1"
                        :placeholder="t('billing.admin.coupons.unlimited')"
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="coupon_max_user">{{
                        t('billing.admin.coupons.max_per_user')
                    }}</Label>
                    <Input
                        id="coupon_max_user"
                        v-model.number="couponForm.max_per_user"
                        type="number"
                        min="1"
                        :placeholder="t('billing.admin.coupons.unlimited')"
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="coupon_until">{{
                        t('billing.admin.coupons.valid_until')
                    }}</Label>
                    <Input
                        id="coupon_until"
                        v-model="couponForm.valid_until"
                        type="datetime-local"
                    />
                </div>

                <div class="space-y-1.5 sm:col-span-2 lg:col-span-3">
                    <Label for="coupon_note">{{
                        t('billing.admin.note')
                    }}</Label>
                    <Input
                        id="coupon_note"
                        v-model="couponForm.note"
                        :placeholder="t('billing.admin.note_placeholder')"
                    />
                </div>

                <div class="flex items-end">
                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="couponForm.processing"
                    >
                        {{ t('billing.admin.coupons.create') }}
                    </Button>
                </div>

                <div class="sm:col-span-2 lg:col-span-4">
                    <InputError
                        :message="
                            couponForm.errors.code ||
                            couponForm.errors.kind ||
                            couponForm.errors.percent_off ||
                            couponForm.errors.amount_off_usd ||
                            couponForm.errors.user_email ||
                            couponForm.errors.max_redemptions ||
                            couponForm.errors.max_per_user ||
                            couponForm.errors.valid_until ||
                            couponForm.errors.note
                        "
                    />
                </div>
            </form>

            <p v-if="coupons.length === 0" class="mt-4 text-sm text-[#989898]">
                {{ t('billing.admin.coupons.empty') }}
            </p>

            <ul v-else class="mt-4 divide-y divide-white/5">
                <li
                    v-for="coupon in coupons"
                    :key="coupon.id"
                    class="flex flex-wrap items-center justify-between gap-3 py-3"
                >
                    <div class="min-w-0">
                        <p class="text-sm text-white">
                            <code
                                dir="ltr"
                                class="font-mono [unicode-bidi:isolate]"
                                >{{ coupon.code }}</code
                            >
                            <span class="text-[#6f6f6f]" dir="ltr">
                                · {{ couponValue(coupon) }}</span
                            >
                        </p>
                        <p class="mt-0.5 text-xs text-[#6f6f6f]">
                            {{
                                coupon.user_email ??
                                t('billing.admin.coupons.anyone')
                            }}
                            · {{ couponUsage(coupon) }}
                            ·
                            {{
                                coupon.valid_until
                                    ? coupon.valid_until.slice(0, 10)
                                    : t('billing.admin.coupons.never_expires')
                            }}
                        </p>
                        <p
                            v-if="coupon.note"
                            class="mt-0.5 text-xs text-[#6f6f6f]"
                        >
                            {{ coupon.note }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <span
                            class="rounded-full px-3 py-1 text-xs ring-1"
                            :class="
                                coupon.disabled || coupon.expired
                                    ? toneClasses.neutral
                                    : toneClasses.positive
                            "
                        >
                            {{ couponStatus(coupon) }}
                        </span>

                        <Button
                            size="sm"
                            variant="ghost"
                            @click="
                                router.post(
                                    coupon.disabled
                                        ? enableCoupon(coupon.id).url
                                        : disableCoupon(coupon.id).url,
                                    {},
                                    { preserveScroll: true },
                                )
                            "
                        >
                            {{
                                coupon.disabled
                                    ? t('billing.admin.coupons.enable')
                                    : t('billing.admin.coupons.disable')
                            }}
                        </Button>

                        <!-- Offered only for a code nobody ever used. Anything
                             redeemed is disabled instead, so the ledger keeps
                             pointing at something. -->
                        <Button
                            v-if="coupon.deletable"
                            size="sm"
                            variant="ghost"
                            @click="couponToDelete = coupon"
                        >
                            {{ t('billing.admin.coupons.delete') }}
                        </Button>
                    </div>
                </li>
            </ul>
        </section>

        <section class="rounded-[16px] bg-[#1a1a1a] p-6 ring-1 ring-white/10">
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

        <ConfirmDeleteModal
            :open="couponToDelete !== null"
            :title="
                t('billing.admin.coupons.delete_title', {
                    code: couponToDelete?.code ?? '',
                })
            "
            :description="t('billing.admin.coupons.delete_description')"
            @update:open="couponToDelete = null"
            @confirm="confirmDeleteCoupon"
        />
    </div>
</template>
