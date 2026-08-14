<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Check, Copy, ExternalLink, Wallet } from 'lucide-vue-next';
import QRCode from 'qrcode';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import SettingsSection from '@/components/settings/SettingsSection.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatAppDate } from '@/lib/date';
import {
    cancel as cancelPayment,
    proof as submitProof,
    store as startPayment,
} from '@/routes/billing/payments';
import type {
    AssetOption,
    NetworkOption,
    PaymentRecord,
    PlanCard,
    PreferredRail,
    SettlementAssetKey,
} from '@/types/billing';

const props = defineProps<{
    plans: PlanCard[];
    networks: NetworkOption[];
    pending: PaymentRecord | null;
    payments: PaymentRecord[];
    preferred: PreferredRail | null;
    status: string | null;
}>();

const { t } = useI18n();
const page = usePage();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Billing', href: '/settings/billing' }],
    },
});

const subscription = computed(() => page.props.subscription);
const calendar = computed(() => page.props.calendar);

/**
 * Open on whatever the buyer used last, falling back to the first thing on
 * offer. Both sides are checked against what is actually available, so a
 * network or asset withdrawn since cannot strand somebody on a rail that no
 * longer takes payments.
 */
const initialNetwork =
    props.networks.find((option) => option.key === props.preferred?.network) ??
    props.networks[0];

const network = ref<NetworkOption | undefined>(initialNetwork);
const asset = ref<SettlementAssetKey | undefined>(
    initialNetwork?.assets.find(
        (option) => option.key === props.preferred?.asset,
    )?.key ?? initialNetwork?.assets[0]?.key,
);

const selectedAsset = computed<AssetOption | undefined>(() =>
    network.value?.assets.find((option) => option.key === asset.value),
);

/**
 * Switching chain re-picks the asset, because the same symbol is a different
 * contract on each one and an asset offered here may not be offered there.
 * Keeping the current choice where it still exists avoids resetting a
 * deliberate selection for no reason.
 */
function chooseNetwork(option: NetworkOption): void {
    network.value = option;

    if (!option.assets.some((candidate) => candidate.key === asset.value)) {
        asset.value = option.assets[0]?.key;
    }
}

const startForm = useForm({ plan: '', network: '', asset: '' });
const proofForm = useForm({ tx_hash: '' });

function choosePlan(plan: PlanCard): void {
    if (!network.value || !asset.value) {
        return;
    }

    startForm.plan = plan.key;
    startForm.network = network.value.key;
    startForm.asset = asset.value;

    startForm.post(startPayment().url, { preserveScroll: true });
}

function submitHash(): void {
    if (!props.pending) {
        return;
    }

    proofForm.post(submitProof(props.pending.id).url, {
        preserveScroll: true,
        onSuccess: () => proofForm.reset(),
    });
}

function cancel(): void {
    if (!props.pending) {
        return;
    }

    router.delete(cancelPayment(props.pending.id).url, {
        preserveScroll: true,
    });
}

const copied = ref<string | null>(null);
let copyTimer: ReturnType<typeof setTimeout> | undefined;

async function copy(value: string, key: string): Promise<void> {
    await navigator.clipboard.writeText(value);
    copied.value = key;

    clearTimeout(copyTimer);
    copyTimer = setTimeout(() => (copied.value = null), 2000);
}

/**
 * A payment being checked settles on its own, so the page follows it rather
 * than asking the user to refresh. Polling stops the moment it reaches a state
 * nothing will move it out of.
 */
let poll: ReturnType<typeof setInterval> | undefined;

watch(
    () => props.pending?.status,
    (status) => {
        clearInterval(poll);

        if (status === 'submitted') {
            poll = setInterval(
                () => router.reload({ only: ['pending', 'payments'] }),
                15000,
            );
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    clearInterval(poll);
    clearTimeout(copyTimer);
});

function shortDate(value: string | null): string {
    return value ? formatAppDate(value.slice(0, 10), calendar.value) : '';
}

/**
 * A scannable version of the payment request.
 *
 * Encodes the EIP-681 URI rather than the bare address, so a scan fills in the
 * amount as well — which matters more here than usual, because the amount has
 * to match to the last decimal and a hand-typed eighteen-decimal figure will
 * not.
 *
 * Rendered on mount rather than in an immediate watcher: setup also runs during
 * SSR, where there is no canvas to draw on.
 */
const qrDataUrl = ref<string | null>(null);

async function renderQr(): Promise<void> {
    const value = props.pending?.payment_uri ?? props.pending?.pay_to_address;

    if (!value) {
        qrDataUrl.value = null;

        return;
    }

    try {
        qrDataUrl.value = await QRCode.toDataURL(value, {
            errorCorrectionLevel: 'M',
            margin: 2,
            width: 320,
            // Dark modules on white, deliberately, rather than themed to the
            // page: inverted or low-contrast codes are the ones scanners fail on.
            color: { dark: '#000000', light: '#ffffff' },
        });
    } catch {
        // A missing QR is a smaller problem than a broken page — the address and
        // amount are both right there to copy.
        qrDataUrl.value = null;
    }
}

onMounted(renderQr);
watch(
    () => props.pending?.payment_uri ?? props.pending?.pay_to_address,
    renderQr,
);

const daysLeft = computed<number | null>(() => {
    const until = subscription.value?.pro_until;

    if (!until) {
        return null;
    }

    const ms = new Date(until).getTime() - Date.now();

    return ms <= 0 ? null : Math.ceil(ms / 86_400_000);
});

const toneClasses: Record<string, string> = {
    positive: 'bg-[#1f2e22] text-[#7BD88F] ring-[#7BD88F]/20',
    pending: 'bg-[#2b2618] text-[#E0B341] ring-[#E0B341]/20',
    negative: 'bg-[#2c1b1b] text-[#E94E50] ring-[#E94E50]/20',
    neutral: 'bg-white/5 text-[#989898] ring-white/10',
};
</script>

<template>
    <Head :title="t('billing.title')" />

    <div class="space-y-6">
        <div>
            <p
                class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
            >
                {{ t('billing.title') }}
            </p>
            <p class="mt-1 text-sm text-[#989898]">
                {{ t('billing.description') }}
            </p>
        </div>

        <p
            v-if="status"
            class="rounded-xl bg-[#1f2e22] px-4 py-3 text-sm text-[#7BD88F] ring-1 ring-[#7BD88F]/20"
        >
            {{ status }}
        </p>

        <!-- Current state -->
        <SettingsSection
            :title="
                subscription?.is_pro
                    ? t('billing.state.pro_title')
                    : subscription?.pro_until
                      ? t('billing.state.expired_title')
                      : t('billing.state.free_title')
            "
        >
            <div class="flex flex-wrap items-center gap-3">
                <span
                    class="rounded-full px-3 py-1 text-xs font-medium ring-1"
                    :class="
                        subscription?.is_pro
                            ? toneClasses.positive
                            : toneClasses.neutral
                    "
                >
                    {{
                        subscription?.is_pro
                            ? t('billing.state.pro_badge')
                            : t('billing.state.free_badge')
                    }}
                </span>

                <span
                    v-if="subscription?.is_pro"
                    class="text-sm text-[#989898]"
                >
                    {{
                        t('billing.state.expires_on', {
                            date: shortDate(subscription.pro_until),
                        })
                    }}
                    <template v-if="daysLeft !== null">
                        ·
                        {{
                            daysLeft === 1
                                ? t('billing.state.day_left')
                                : t('billing.state.days_left', {
                                      days: daysLeft,
                                  })
                        }}
                    </template>
                </span>

                <span
                    v-else-if="subscription?.pro_until"
                    class="text-sm text-[#989898]"
                >
                    {{
                        t('billing.state.expired_on', {
                            date: shortDate(subscription.pro_until),
                        })
                    }}
                </span>

                <span v-else class="text-sm text-[#989898]">
                    {{ t('billing.state.free_body') }}
                </span>
            </div>

            <!-- Load-bearing, not decorative: nothing renews itself, and a user
                 expecting card-like renewal simply loses access. -->
            <p class="mt-4 max-w-[68ch] text-sm text-[#989898]">
                {{ t('billing.state.no_auto_renew') }}
            </p>
        </SettingsSection>

        <!-- Asset and chain -->
        <SettingsSection
            v-if="!pending"
            :title="t('billing.assets.heading')"
            :description="
                selectedAsset?.is_stable
                    ? t('billing.assets.stable_hint')
                    : t('billing.assets.volatile_hint')
            "
        >
            <!-- Only shown once there is a choice to make. A single-chain
                 install should not have to answer a question with one answer. -->
            <div v-if="networks.length > 1" class="mb-5">
                <p class="mb-2 text-xs text-[#989898]">
                    {{ t('billing.networks.heading') }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="option in networks"
                        :key="option.key"
                        type="button"
                        :aria-pressed="option.key === network?.key"
                        :class="[
                            'cursor-pointer rounded-xl px-4 py-2 text-sm ring-1 transition-colors duration-200',
                            'focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none',
                            option.key === network?.key
                                ? 'bg-[#02CD86]/10 font-medium text-[#02CD86] ring-[#02CD86]/30'
                                : 'text-[#989898] ring-white/10 hover:bg-white/5 hover:text-white',
                        ]"
                        @click="chooseNetwork(option)"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    v-for="option in network?.assets ?? []"
                    :key="option.key"
                    type="button"
                    :aria-pressed="option.key === asset"
                    :class="[
                        'cursor-pointer rounded-xl px-4 py-2 text-sm ring-1 transition-colors duration-200',
                        'focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none',
                        option.key === asset
                            ? 'bg-[#02CD86]/10 font-medium text-[#02CD86] ring-[#02CD86]/30'
                            : 'text-[#989898] ring-white/10 hover:bg-white/5 hover:text-white',
                    ]"
                    @click="asset = option.key"
                >
                    {{ option.symbol }}
                    <span
                        class="ml-1 text-xs"
                        :class="
                            option.key === asset
                                ? 'text-[#02CD86]/60'
                                : 'text-[#6f6f6f]'
                        "
                        >{{ option.label }}</span
                    >
                </button>
            </div>

            <p v-if="network" class="mt-3 text-xs text-[#6f6f6f]">
                {{ network.label }} ·
                {{ t('billing.networks.chain_id', { id: network.chain_id }) }}
            </p>
        </SettingsSection>

        <!-- Plans -->
        <SettingsSection
            v-if="!pending"
            :title="t('billing.plans.heading')"
            :description="t('billing.plans.gas_hint')"
        >
            <div class="grid gap-3 sm:grid-cols-3">
                <div
                    v-for="plan in plans"
                    :key="plan.key"
                    class="flex flex-col rounded-2xl p-4 ring-1"
                    :class="
                        plan.highlighted
                            ? 'bg-white/[0.06] ring-white/25'
                            : 'bg-white/[0.02] ring-white/10'
                    "
                >
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="text-[15px] font-medium text-white">
                            {{ plan.label }}
                        </h3>
                        <span
                            v-if="plan.savings_percent"
                            class="rounded-full bg-[#1f2e22] px-2 py-0.5 text-[11px] text-[#7BD88F] ring-1 ring-[#7BD88F]/20"
                        >
                            {{
                                t('billing.plans.save', {
                                    percent: plan.savings_percent,
                                })
                            }}
                        </span>
                    </div>

                    <p class="mt-1 text-xs text-[#989898]">
                        {{ plan.description }}
                    </p>

                    <p class="mt-3 text-2xl font-medium text-white" dir="ltr">
                        ${{ plan.price_usd }}
                    </p>
                    <p class="text-xs text-[#6f6f6f]" dir="ltr">
                        {{
                            t('billing.plans.per_month', {
                                amount: `$${plan.per_month_usd}`,
                            })
                        }}
                    </p>

                    <p class="mt-3 text-xs text-[#989898]">
                        {{
                            subscription?.is_pro
                                ? t('billing.plans.extends', {
                                      months:
                                          plan.months === 1
                                              ? t('billing.plans.month')
                                              : t('billing.plans.months', {
                                                    count: plan.months,
                                                }),
                                  })
                                : t('billing.plans.starts')
                        }}
                    </p>

                    <Button
                        class="mt-4"
                        :disabled="startForm.processing || !asset"
                        @click="choosePlan(plan)"
                    >
                        {{ t('billing.plans.choose') }}
                    </Button>
                </div>
            </div>

            <p v-if="startForm.errors.plan" class="mt-3 text-sm text-[#E94E50]">
                {{ startForm.errors.plan }}
            </p>
        </SettingsSection>

        <!-- Open payment -->
        <SettingsSection v-if="pending" :title="t('billing.pay.heading')">
            <p
                class="rounded-xl bg-[#2b2618] px-4 py-3 text-sm text-[#E0B341] ring-1 ring-[#E0B341]/20"
            >
                {{
                    t('billing.pay.network_warning', {
                        asset: pending.asset_symbol,
                        network: pending.network_label ?? '',
                    })
                }}
            </p>

            <div class="mt-4 space-y-4">
                <div>
                    <p class="text-xs text-[#989898]">
                        {{ t('billing.pay.amount_label') }}
                    </p>
                    <div class="mt-1 flex items-center gap-2">
                        <!-- dir=ltr with bidi isolation is not cosmetic: an
                             amount or address reordered by an RTL paragraph is
                             one a user copies by eye and gets wrong, and the
                             funds do not come back. -->
                        <code
                            dir="ltr"
                            class="flex-1 rounded-lg bg-black/40 px-3 py-2 font-mono text-sm break-all text-white [unicode-bidi:isolate]"
                        >
                            {{ pending.expected_amount }}
                            {{ pending.asset_symbol }}
                        </code>
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="t('billing.pay.copy')"
                            @click="copy(pending.expected_amount, 'amount')"
                        >
                            <Check
                                v-if="copied === 'amount'"
                                class="size-4 text-[#7BD88F]"
                            />
                            <Copy v-else class="size-4" />
                        </Button>
                    </div>
                    <p class="mt-1 text-xs text-[#6f6f6f]">
                        {{ t('billing.pay.exact_amount') }}
                    </p>
                </div>

                <div>
                    <p class="text-xs text-[#989898]">
                        {{ t('billing.pay.address_label') }}
                    </p>
                    <div class="mt-1 flex items-center gap-2">
                        <code
                            dir="ltr"
                            class="flex-1 rounded-lg bg-black/40 px-3 py-2 font-mono text-sm break-all text-white [unicode-bidi:isolate]"
                        >
                            {{ pending.pay_to_address }}
                        </code>
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="t('billing.pay.copy')"
                            @click="
                                copy(pending.pay_to_address ?? '', 'address')
                            "
                        >
                            <Check
                                v-if="copied === 'address'"
                                class="size-4 text-[#7BD88F]"
                            />
                            <Copy v-else class="size-4" />
                        </Button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <!-- White plate behind the code on purpose: the page is dark,
                         and a themed or inverted QR is the kind scanners fail on. -->
                    <img
                        v-if="qrDataUrl"
                        :src="qrDataUrl"
                        :alt="t('billing.pay.scan')"
                        width="160"
                        height="160"
                        class="rounded-xl bg-white p-2"
                    />

                    <div class="space-y-2">
                        <p class="text-xs text-[#6f6f6f]">
                            {{ t('billing.pay.scan_hint') }}
                        </p>
                        <a
                            v-if="pending.payment_uri"
                            :href="pending.payment_uri"
                            class="inline-flex items-center gap-2 text-sm text-white underline-offset-4 hover:underline"
                        >
                            <Wallet class="size-4" />
                            {{ t('billing.pay.open_wallet') }}
                        </a>
                    </div>
                </div>

                <p class="text-xs text-[#6f6f6f]">
                    {{
                        t('billing.pay.window_closes', {
                            time: shortDate(pending.expires_at),
                        })
                    }}
                    <template v-if="pending.quote_expires_at">
                        ·
                        {{
                            t('billing.pay.quote_closes', {
                                time: shortDate(pending.quote_expires_at),
                            })
                        }}
                    </template>
                </p>

                <div
                    v-if="pending.status === 'submitted'"
                    class="rounded-xl bg-[#2b2618] px-4 py-3 text-sm text-[#E0B341] ring-1 ring-[#E0B341]/20"
                >
                    {{ t('billing.pay.checking') }}
                    <template v-if="pending.confirmations !== null">
                        ·
                        {{
                            t('billing.pay.confirmations', {
                                count: pending.confirmations,
                                required: pending.confirmations_required ?? 0,
                            })
                        }}
                    </template>
                </div>

                <form v-else class="space-y-2" @submit.prevent="submitHash">
                    <label for="tx_hash" class="block text-xs text-[#989898]">
                        {{ t('billing.pay.hash_label') }}
                    </label>
                    <Input
                        id="tx_hash"
                        v-model="proofForm.tx_hash"
                        dir="ltr"
                        class="font-mono [unicode-bidi:isolate]"
                        :placeholder="t('billing.pay.hash_placeholder')"
                    />
                    <p class="text-xs text-[#6f6f6f]">
                        {{ t('billing.pay.hash_hint') }}
                    </p>
                    <p
                        v-if="proofForm.errors.tx_hash"
                        class="text-sm text-[#E94E50]"
                    >
                        {{ proofForm.errors.tx_hash }}
                    </p>

                    <div class="flex items-center gap-3 pt-1">
                        <Button type="submit" :disabled="proofForm.processing">
                            {{ t('billing.pay.submit') }}
                        </Button>
                        <button
                            type="button"
                            class="text-sm text-[#989898] hover:text-white"
                            @click="cancel"
                        >
                            {{ t('billing.pay.cancel') }}
                        </button>
                    </div>
                </form>
            </div>
        </SettingsSection>

        <!-- History -->
        <SettingsSection :title="t('billing.history.heading')">
            <p v-if="payments.length === 0" class="text-sm text-[#989898]">
                {{ t('billing.history.empty') }}
            </p>

            <ul v-else class="divide-y divide-white/5">
                <li
                    v-for="payment in payments"
                    :key="payment.id"
                    class="flex flex-wrap items-center justify-between gap-3 py-3"
                >
                    <div class="min-w-0">
                        <p class="text-sm text-white">
                            {{ payment.plan_label }}
                            <span class="text-[#6f6f6f]" dir="ltr"
                                >· ${{ payment.price_usd }}</span
                            >
                        </p>
                        <p class="mt-0.5 text-xs text-[#6f6f6f]">
                            {{
                                payment.verified_at
                                    ? t('billing.history.paid_on', {
                                          date: shortDate(payment.verified_at),
                                      })
                                    : t('billing.history.opened_on', {
                                          date: shortDate(payment.created_at),
                                      })
                            }}
                        </p>
                        <p
                            v-if="payment.failure_message"
                            class="mt-0.5 text-xs text-[#E94E50]"
                        >
                            {{ payment.failure_message }}
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
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
                </li>
            </ul>
        </SettingsSection>
    </div>
</template>
