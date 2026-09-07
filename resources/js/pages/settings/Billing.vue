<script setup lang="ts">
import { Head, router, useForm, useHttp, usePage } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Check,
    Copy,
    ExternalLink,
    PartyPopper,
    Receipt,
    Sparkles,
    Ticket,
    Wallet,
} from 'lucide-vue-next';
import QRCode from 'qrcode';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import SettingsSection from '@/components/settings/SettingsSection.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatAppDate } from '@/lib/date';
import { edit as billingEdit } from '@/routes/billing';
import { preview as previewCoupon } from '@/routes/billing/coupon';
import {
    cancel as cancelPayment,
    proof as submitProof,
    store as startPayment,
} from '@/routes/billing/payments';
import type {
    ActivationReceipt,
    AssetOption,
    MilesPackKey,
    CouponPreview,
    CouponPreviewResponse,
    HistoryEntry,
    NetworkOption,
    PaymentRecord,
    PlanCard,
    PreferredRail,
    SettlementAssetKey,
} from '@/types/billing';

const props = defineProps<{
    plans: PlanCard[];
    balance: number;
    networks: NetworkOption[];
    pending: PaymentRecord | null;
    history: HistoryEntry[];
    preferred: PreferredRail | null;
    status: string | null;
    activated: ActivationReceipt | null;
}>();

const { t } = useI18n();
const page = usePage();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Billing', href: '/settings/billing' }],
    },
});

const balance = computed(() => page.props.miles?.balance ?? props.balance);
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

const startForm = useForm({ plan: '', network: '', asset: '', coupon: '' });

/**
 * The plan being bought, or null while the buyer is still choosing.
 *
 * Picking a plan used to be the last thing that happened: the coupon box sat
 * below the plan grid, so "Choose" was the first control you reached and the
 * code was something you found afterwards, if you scrolled. Selecting a plan
 * now opens a checkout step instead of firing the purchase, which puts the
 * code, the total and the confirm button in the order they are decided in.
 */
const selectedPlanKey = ref<MilesPackKey | null>(
    props.plans.find((plan) => plan.key === props.pending?.plan)?.key ??
        props.plans.find((plan) => plan.highlighted)?.key ??
        props.plans[0]?.key ??
        null,
);

const selectedPlan = computed<PlanCard | null>(
    () =>
        props.plans.find((plan) => plan.key === selectedPlanKey.value) ?? null,
);

function selectPlan(plan: PlanCard): void {
    selectedPlanKey.value = plan.key;
    startForm.clearErrors();
}

/**
 * The coupon the buyer has checked, and what it is worth against each plan.
 *
 * Checked before committing to a plan rather than discovered afterwards: a code
 * that silently does nothing until you have already picked something is
 * indistinguishable from a broken one. The preview claims no use, so it costs
 * nothing to call — the authoritative check still happens under a lock when the
 * intent is opened.
 */
const appliedCoupon = ref<CouponPreview | null>(null);
const couponError = ref<string | null>(null);
const checkingCoupon = ref(false);

const couponCheck = useHttp<{ coupon: string }, CouponPreviewResponse>({
    coupon: '',
});

async function applyCoupon(): Promise<void> {
    const code = startForm.coupon.trim();

    couponError.value = null;
    appliedCoupon.value = null;

    if (code === '') {
        return;
    }

    couponCheck.coupon = code;
    checkingCoupon.value = true;

    try {
        const result = await couponCheck.post(previewCoupon.url());

        if (result.accepted) {
            appliedCoupon.value = { code: result.code, plans: result.plans };
        } else {
            couponError.value = result.message;
        }
    } catch {
        couponError.value = t('billing.errors.generic');
    } finally {
        checkingCoupon.value = false;
    }
}

function clearCoupon(): void {
    startForm.coupon = '';
    appliedCoupon.value = null;
    couponError.value = null;
}

/** What a plan costs once the checked coupon is taken into account. */
function pricedPlan(plan: PlanCard): {
    price: string;
    was: string | null;
    free: boolean;
} {
    const discounted = appliedCoupon.value?.plans[plan.key];

    if (discounted === undefined) {
        return { price: plan.price_usd, was: null, free: false };
    }

    return {
        price: discounted.final_price_usd,
        was: discounted.list_price_usd,
        free: discounted.covers_everything,
    };
}

/**
 * What the buyer owes for the plan they picked, coupon included.
 *
 * Null until a plan is selected. `free` is the branch that changes the shape of
 * the whole step: no chain, no asset, no amount, and a confirm button that
 * activates rather than opening a payment.
 */
const checkout = computed<{
    listPrice: string;
    discount: string | null;
    total: string;
    free: boolean;
} | null>(() => {
    const plan = selectedPlan.value;

    if (plan === null) {
        return null;
    }

    const priced = pricedPlan(plan);

    return {
        listPrice: plan.price_usd,
        discount: appliedCoupon.value?.plans[plan.key]?.discount_usd ?? null,
        total: priced.price,
        free: priced.free,
    };
});

// A fixed-amount code can zero the monthly plan and still leave the yearly one
// owing, so this is asked of the selected plan rather than of the code.
const nothingToPay = computed<boolean>(() => checkout.value?.free === true);

const proofForm = useForm({ tx_hash: '' });

/**
 * Commits the selected plan — as a payment intent, or as an outright grant when
 * a coupon covers the whole price.
 *
 * Both go through the same endpoint on purpose. Which branch runs is the
 * server's call, decided under a lock against the coupon's remaining uses; a
 * client that routed itself to the free path would be deciding entitlement from
 * a preview that was only ever advisory.
 *
 * The rail travels even on the free path. Nothing on the server reads it there,
 * but `StartPaymentRequest` validates every field on every request, and a
 * half-filled payload would fail before reaching the branch that ignores it.
 */
function confirmSelectedPlan(): void {
    const plan = selectedPlan.value;

    if (
        plan === null ||
        !network.value ||
        !asset.value ||
        hasUncheckedCoupon.value ||
        startForm.processing
    ) {
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
const copyError = ref(false);
const hasUncheckedCoupon = computed(
    () =>
        startForm.coupon.trim() !== '' &&
        appliedCoupon.value?.code !== startForm.coupon.trim().toUpperCase(),
);
const settledPayment = ref<HistoryEntry | null>(null);
watch(
    () => [props.pending, props.history] as const,
    ([pending, history], [previous]) => {
        if (previous && !pending) {
            settledPayment.value =
                history.find(
                    (entry) =>
                        entry.id === previous.id &&
                        entry.tone === 'positive' &&
                        entry.miles !== null,
                ) ?? null;
        }
    },
);
let copyTimer: ReturnType<typeof setTimeout> | undefined;

async function copy(value: string, key: string): Promise<void> {
    copyError.value = false;

    try {
        await navigator.clipboard.writeText(value);
    } catch {
        copyError.value = true;

        return;
    }

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
                () =>
                    router.reload({
                        only: ['pending', 'history', 'balance', 'miles'],
                    }),
                10000,
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

const toneClasses: Record<string, string> = {
    positive: 'bg-[#1f2e22] text-[#7BD88F] ring-[#7BD88F]/20',
    pending: 'bg-[#2b2618] text-[#d9c48f] ring-[#d9c48f]/20',
    negative: 'bg-[#2c1b1b] text-[#E94E50] ring-[#E94E50]/20',
    neutral: 'bg-white/5 text-[#989898] ring-white/10',
};

/**
 * The confirmation for a coupon that paid for everything.
 *
 * A full-price redemption opens no intent and produces no payment record, so
 * the page it lands back on has nothing new on it beyond a Pro badge that was
 * grey a second ago. Without something that says so outright, the buyer is left
 * to infer from a changed badge that a code they just typed worked. This says
 * it, then sends them back to a clean billing page.
 */
const activation = ref<ActivationReceipt | null>(props.activated);

watch(
    () => props.activated,
    (receipt) => {
        if (receipt !== null) {
            activation.value = receipt;
        }
    },
);

function dismissActivation(): void {
    activation.value = null;
    selectedPlanKey.value = null;
    clearCoupon();

    // A fresh visit rather than `reload()`: the flash has been consumed, and
    // the buyer should land on the billing page as it will look from now on
    // rather than on the same render with a dialog taken off the top.
    router.visit(billingEdit().url);
}
</script>

<template>
    <div class="flex flex-col gap-[14px]">
        <Head :title="t('billing.title')" />
        <p
            v-if="status"
            class="rounded-xl bg-[#1f2e22] px-4 py-3 text-sm text-[#7BD88F] ring-1 ring-[#7BD88F]/20"
        >
            {{ status }}
        </p>

        <p
            v-if="settledPayment"
            role="status"
            class="rounded-xl border border-[#02cd86]/20 bg-[#02cd86]/10 p-4 text-sm text-[#7BD88F]"
        >
            {{
                t('billing.packs.credited', {
                    miles: settledPayment.miles?.toLocaleString(),
                })
            }}
        </p>
        <SettingsSection
            :icon="Sparkles"
            :title="t('billing.packs.heading')"
            :description="t('billing.packs.description')"
        >
            <template #actions
                ><span class="text-sm text-[#d9c48f]">{{
                    t('billing.packs.balance', {
                        miles: balance.toLocaleString(),
                    })
                }}</span></template
            >
            <div
                class="grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4"
                role="group"
                :aria-label="t('billing.packs.heading')"
            >
                <button
                    v-for="plan in plans"
                    :key="plan.key"
                    type="button"
                    :disabled="!!pending || startForm.processing"
                    :aria-pressed="plan.key === selectedPlanKey"
                    class="flex min-w-0 cursor-pointer flex-col rounded-2xl border p-3 text-start transition-colors duration-200 hover:bg-[#d9c48f]/5 focus-visible:ring-2 focus-visible:ring-[#d9c48f] focus-visible:outline-none disabled:cursor-default sm:p-5"
                    :class="
                        plan.key === selectedPlanKey
                            ? 'border-[#d9c48f] bg-[#d9c48f]/8'
                            : 'border-white/10 bg-[#1a1a1a]'
                    "
                    @click="selectPlan(plan)"
                >
                    <span
                        class="mb-2 flex min-h-6 items-center text-[10px] font-medium text-[#d9c48f] sm:text-[11px]"
                    >
                        <span
                            v-if="plan.highlighted || plan.best_value"
                            class="rounded-full bg-[#d9c48f]/10 px-2 py-1"
                            >{{
                                t(
                                    plan.best_value
                                        ? 'billing.packs.best_value'
                                        : 'billing.packs.recommended',
                                )
                            }}</span
                        >
                    </span>
                    <span
                        dir="ltr"
                        class="text-[22px] leading-tight font-semibold text-white tabular-nums"
                        >{{ plan.miles.toLocaleString() }}</span
                    >
                    <span class="mt-1 text-xs text-[#989898]">{{
                        t('miles.unit')
                    }}</span>
                    <span dir="ltr" class="mt-3 text-sm text-white tabular-nums"
                        >&#36;{{ plan.price_usd }}</span
                    >
                    <span class="mt-2 text-xs leading-relaxed text-[#989898]">{{
                        t('billing.packs.usage', { count: plan.advisor_plans })
                    }}</span>
                </button>
            </div>
            <p class="mt-4 text-xs text-[#989898]">
                {{ t('billing.packs.no_expiry') }}
            </p>
        </SettingsSection>

        <!-- Step 2 — review, code, confirm. -->
        <SettingsSection
            v-if="!pending && selectedPlan && checkout"
            :icon="Ticket"
            :title="t('billing.checkout.heading')"
            :description="t('billing.checkout.description')"
        >
            <div class="rounded-xl bg-white/4 p-4 text-sm text-[#989898]">
                <p class="font-medium text-white">{{ selectedPlan.label }}</p>
                <p class="mt-1">
                    {{
                        t('billing.packs.balance_after', {
                            miles: (
                                balance + selectedPlan.miles
                            ).toLocaleString(),
                        })
                    }}
                </p>
            </div>

            <!-- Coupon. Second, where a code is something you add to a decision
                 already made, rather than a box you have to find before the
                 prices on screen mean anything. -->
            <div class="mt-4 space-y-1.5">
                <label for="coupon" class="block text-xs text-[#989898]">
                    {{ t('billing.coupon.label') }}
                </label>

                <form
                    class="flex flex-wrap gap-2"
                    @submit.prevent="applyCoupon"
                >
                    <Input
                        id="coupon"
                        v-model="startForm.coupon"
                        dir="ltr"
                        class="settings-input w-full max-w-xs font-mono uppercase [unicode-bidi:isolate]"
                        :placeholder="t('billing.coupon.placeholder')"
                        @input="
                            appliedCoupon = null;
                            couponError = null;
                        "
                    />
                    <Button
                        type="submit"
                        variant="ghost"
                        :disabled="checkingCoupon || startForm.coupon === ''"
                    >
                        {{ t('billing.coupon.apply') }}
                    </Button>
                    <Button
                        v-if="appliedCoupon"
                        type="button"
                        variant="ghost"
                        @click="clearCoupon"
                    >
                        {{ t('billing.coupon.remove') }}
                    </Button>
                </form>

                <p
                    v-if="appliedCoupon && !nothingToPay"
                    class="text-sm text-[#02CD86]"
                >
                    {{
                        t('billing.coupon.applied', {
                            code: appliedCoupon.code,
                        })
                    }}
                </p>

                <p v-if="couponError" class="text-sm text-[#E94E50]">
                    {{ couponError }}
                </p>

                <p
                    v-if="startForm.errors.coupon"
                    class="text-sm text-[#E94E50]"
                >
                    {{ startForm.errors.coupon }}
                </p>
            </div>

            <p v-if="hasUncheckedCoupon" class="mt-3 text-xs text-[#989898]">
                {{ t('billing.packs.coupon_check') }}
            </p>
            <!-- The total, itemised. A discount whose arithmetic is invisible is
                 one the buyer has to take on trust. -->
            <dl
                class="mt-4 space-y-2 rounded-2xl bg-[#141414] p-4 ring-1 ring-white/10"
            >
                <div class="flex items-baseline justify-between gap-4 text-sm">
                    <dt class="text-[#989898]">
                        {{ t('billing.checkout.subtotal') }}
                    </dt>
                    <dd class="text-white" dir="ltr">
                        ${{ checkout.listPrice }}
                    </dd>
                </div>

                <div
                    v-if="appliedCoupon && checkout.discount"
                    class="flex items-baseline justify-between gap-4 text-sm"
                >
                    <dt class="min-w-0 text-[#02CD86]">
                        {{
                            t('billing.checkout.discount', {
                                code: appliedCoupon.code,
                            })
                        }}
                    </dt>
                    <dd class="text-[#02CD86]" dir="ltr">
                        −${{ checkout.discount }}
                    </dd>
                </div>

                <div
                    class="flex items-baseline justify-between gap-4 border-t border-white/10 pt-2"
                >
                    <dt class="text-sm font-medium text-white">
                        {{ t('billing.checkout.total') }}
                    </dt>
                    <dd
                        class="text-xl font-medium"
                        :class="nothingToPay ? 'text-[#02CD86]' : 'text-white'"
                        dir="ltr"
                    >
                        ${{ checkout.total }}
                    </dd>
                </div>
            </dl>

            <!-- Nothing to pay: say so before the button does, and drop the rail
                 entirely rather than asking which chain to send zero on. -->
            <div
                v-if="nothingToPay"
                class="mt-4 flex gap-3 rounded-2xl bg-[#02CD86]/8 p-4 ring-1 ring-[#02CD86]/25"
            >
                <span
                    class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#02CD86]/15 text-[#02CD86]"
                >
                    <BadgeCheck class="size-5" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-[#02CD86]">
                        {{ t('billing.checkout.nothing_to_pay_title') }}
                    </p>
                    <p class="mt-1 text-sm leading-relaxed text-white/75">
                        {{
                            t('billing.checkout.nothing_to_pay_body', {
                                plan: selectedPlan.label,
                            })
                        }}
                    </p>
                </div>
            </div>

            <!-- Rail. Only when there is something to transfer. -->
            <div v-else class="mt-4">
                <p class="text-xs text-[#989898]">
                    {{ t('billing.assets.heading') }}
                </p>

                <div
                    v-if="networks.length > 1"
                    class="mt-2 flex flex-wrap gap-2"
                >
                    <button
                        v-for="option in networks"
                        :key="option.key"
                        type="button"
                        :aria-pressed="option.key === network?.key"
                        :class="[
                            'min-h-11 cursor-pointer rounded-xl px-4 text-sm ring-1 transition-colors duration-200',
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

                <div class="mt-2 flex flex-wrap gap-2">
                    <button
                        v-for="option in network?.assets ?? []"
                        :key="option.key"
                        type="button"
                        :aria-pressed="option.key === asset"
                        :class="[
                            'min-h-11 cursor-pointer rounded-xl px-4 text-sm ring-1 transition-colors duration-200',
                            'focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none',
                            option.key === asset
                                ? 'bg-[#02CD86]/10 font-medium text-[#02CD86] ring-[#02CD86]/30'
                                : 'text-[#989898] ring-white/10 hover:bg-white/5 hover:text-white',
                        ]"
                        @click="asset = option.key"
                    >
                        {{ option.symbol }}
                        <span
                            class="ms-1 text-xs"
                            :class="
                                option.key === asset
                                    ? 'text-[#02CD86]/60'
                                    : 'text-[#989898]'
                            "
                            >{{ option.label }}</span
                        >
                    </button>
                </div>

                <p class="mt-2 text-xs text-[#989898]">
                    <template v-if="network">
                        {{ network.label }} ·
                        {{
                            t('billing.networks.chain_id', {
                                id: network.chain_id,
                            })
                        }}
                        ·
                    </template>
                    {{
                        selectedAsset?.is_stable
                            ? t('billing.assets.stable_hint')
                            : t('billing.assets.volatile_hint')
                    }}
                </p>
            </div>

            <p v-if="startForm.errors.plan" class="mt-3 text-sm text-[#E94E50]">
                {{ startForm.errors.plan }}
            </p>

            <template #footer>
                <div class="flex flex-wrap items-center gap-3">
                    <Button
                        class="min-h-11 w-full bg-[#d9c48f] text-[#101010] hover:bg-[#e5d4ab]"
                        :disabled="
                            startForm.processing ||
                            checkingCoupon ||
                            hasUncheckedCoupon ||
                            !asset
                        "
                        @click="confirmSelectedPlan"
                    >
                        <Spinner v-if="startForm.processing" class="size-4" />
                        {{
                            nothingToPay
                                ? t('billing.checkout.confirm_free')
                                : t('billing.checkout.confirm_paid')
                        }}
                    </Button>
                </div>
            </template>
        </SettingsSection>

        <!-- Open payment -->
        <SettingsSection
            v-if="pending"
            :icon="Wallet"
            :title="t('billing.pay.heading')"
        >
            <p
                class="rounded-xl bg-[#2b2618] px-4 py-3 text-sm text-[#d9c48f] ring-1 ring-[#d9c48f]/20"
            >
                {{
                    t('billing.pay.network_warning', {
                        asset: pending.asset_symbol,
                        network: pending.network_label ?? '',
                    })
                }}
            </p>

            <p
                v-if="copyError"
                role="alert"
                class="mt-3 text-sm text-[#E94E50]"
            >
                {{ t('billing.packs.copy_failed') }}
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
                    <p class="mt-1 text-xs text-[#989898]">
                        {{ t('billing.pay.exact_amount') }}
                    </p>

                    <p
                        v-if="pending.list_price_usd && pending.coupon_code"
                        class="mt-1 text-xs text-[#02CD86]"
                    >
                        {{
                            t('billing.coupon.applied', {
                                code: pending.coupon_code,
                            })
                        }}
                        <span class="text-[#989898] line-through" dir="ltr">
                            {{
                                t('billing.coupon.was', {
                                    amount: `$${pending.list_price_usd}`,
                                })
                            }}
                        </span>
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
                        <p class="text-xs text-[#989898]">
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

                <p class="text-xs text-[#989898]">
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
                    class="rounded-xl bg-[#2b2618] px-4 py-3 text-sm text-[#d9c48f] ring-1 ring-[#d9c48f]/20"
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
                    <p class="text-xs text-[#989898]">
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
        <!-- Payments and coupon redemptions in one list. A code covering the
             whole price opens no intent, so a history built from payments alone
             showed nothing for it — leaving the abandoned intent the buyer had
             cancelled to go and use the code as the only trace of a purchase
             that actually succeeded. -->
        <SettingsSection :icon="Receipt" :title="t('billing.history.heading')">
            <p v-if="history.length === 0" class="text-sm text-[#989898]">
                {{ t('billing.history.empty') }}
            </p>

            <ul v-else class="divide-y divide-white/5">
                <li
                    v-for="entry in history"
                    :key="entry.id"
                    class="flex flex-wrap items-center justify-between gap-3 py-3"
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <span
                            class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg"
                            :class="
                                entry.kind === 'coupon'
                                    ? 'bg-[#02CD86]/10 text-[#02CD86]'
                                    : 'bg-white/5 text-[#989898]'
                            "
                        >
                            <component
                                :is="entry.kind === 'coupon' ? Ticket : Wallet"
                                class="size-4"
                                aria-hidden="true"
                            />
                        </span>

                        <div class="min-w-0">
                            <p class="text-sm text-white">
                                <!-- A coupon grant records months, not a plan,
                                     so it is titled by the count using the same
                                     keys the rest of the page counts months
                                     with. -->
                                <span
                                    v-if="entry.miles !== null"
                                    class="text-[#d9c48f]"
                                    >{{ entry.tone === 'positive' ? '+' : ''
                                    }}{{ entry.miles.toLocaleString() }}
                                    {{ t('miles.unit') }}</span
                                >
                                <span v-else>{{
                                    entry.plan_label ??
                                    (entry.months === 1
                                        ? t('billing.plans.month')
                                        : t('billing.plans.months', {
                                              count: entry.months,
                                          }))
                                }}</span>
                                <span class="text-[#989898]" dir="ltr"
                                    >· ${{ entry.price_usd }}</span
                                >
                                <span
                                    v-if="entry.list_price_usd"
                                    class="text-[#989898] line-through"
                                    dir="ltr"
                                >
                                    ${{ entry.list_price_usd }}
                                </span>
                            </p>
                            <p class="mt-0.5 text-xs text-[#989898]">
                                {{
                                    entry.settled_at
                                        ? t('billing.history.paid_on', {
                                              date: shortDate(entry.settled_at),
                                          })
                                        : t('billing.history.opened_on', {
                                              date: shortDate(entry.created_at),
                                          })
                                }}
                            </p>
                            <p
                                v-if="entry.network_label"
                                class="mt-1 text-xs text-[#989898]"
                            >
                                {{ entry.network_label }}
                            </p>
                            <p
                                v-if="entry.coupon_code"
                                class="mt-0.5 text-xs text-[#02CD86]"
                            >
                                <!-- "Covered by" only where it actually was.
                                     A partial code on a real payment took
                                     something off the price; it did not pay
                                     it. -->
                                {{
                                    entry.kind === 'coupon'
                                        ? t(
                                              'billing.history.free_with_coupon',
                                              {
                                                  code: entry.coupon_code,
                                              },
                                          )
                                        : t('billing.coupon.applied', {
                                              code: entry.coupon_code,
                                          })
                                }}
                            </p>
                            <p
                                v-if="entry.failure_message"
                                class="mt-0.5 text-xs text-[#E94E50]"
                            >
                                {{ entry.failure_message }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <a
                            v-if="entry.explorer_url"
                            :href="entry.explorer_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 text-xs text-[#989898] hover:text-white"
                        >
                            {{ t('billing.history.view_tx') }}
                            <ExternalLink class="size-3" />
                        </a>
                        <span
                            class="rounded-full px-3 py-1 text-xs ring-1"
                            :class="toneClasses[entry.tone]"
                        >
                            {{ entry.status_label }}
                        </span>
                    </div>
                </li>
            </ul>
        </SettingsSection>

        <Dialog
            :open="activation !== null"
            @update:open="
                (open) => {
                    if (!open) dismissActivation();
                }
            "
        >
            <DialogContent
                class="max-w-sm rounded-2xl bg-[#1a1a1a] p-6 text-center"
            >
                <PartyPopper
                    class="mx-auto size-8 text-[#d9c48f]"
                    aria-hidden="true"
                />
                <DialogTitle>{{ t('billing.activated.title') }}</DialogTitle>
                <DialogDescription v-if="activation">{{
                    t('billing.activated.body', {
                        code: activation.coupon_code,
                        miles: activation.miles.toLocaleString(),
                    })
                }}</DialogDescription>
                <Button
                    class="min-h-11 w-full bg-[#d9c48f] text-[#101010] hover:bg-[#e5d4ab]"
                    @click="dismissActivation"
                    >{{ t('billing.activated.continue') }}</Button
                >
            </DialogContent>
        </Dialog>
    </div>
</template>
