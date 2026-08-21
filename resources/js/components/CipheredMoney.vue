<script setup lang="ts">
import { computed, ref, watchEffect } from 'vue';
import { useAmountMask } from '@/composables/useAmountMask';
import { useVault } from '@/composables/useVault';
import {
    format as formatMoney,
    formatCurrencyDisplay,
    formatCurrencyNumber,
} from '@/lib/money';
import type { CurrencyCode, Rates } from '@/lib/money';
import type { Encrypted } from '@/types/vault';

const props = withDefaults(
    defineProps<{
        amount: Encrypted<string | number> | null | undefined;
        displayAmount?: string | number | null;
        currency: CurrencyCode;
        displayCurrency: CurrencyCode;
        rates: Rates | null;
        /** Include the currency symbol when no table header provides it. */
        showCurrency?: boolean;
        /** Table the amount came from — needed to rebuild the AAD. */
        table?: string;
    }>(),
    { showCurrency: false, table: 'transactions' },
);

const { reveal, revealAsync, trackKey } = useVault();
const { masked } = useAmountMask();

const resolved = ref<string | number | undefined>(reveal(props.amount));

watchEffect(async () => {
    // Re-runs when the vault is unlocked, so an amount sealed at mount resolves
    // in place instead of waiting for a navigation to remount this.
    trackKey();

    const immediate = reveal(props.amount);

    if (immediate !== undefined) {
        resolved.value = immediate;

        return;
    }

    resolved.value = undefined;
    resolved.value = await revealAsync<string | number>(
        props.amount,
        props.table,
        'decimal',
    );
});

// Row-level values remain exact. Compact figures are deliberately applied only
// to summary totals through CompactMoney, where the full value is available in
// a tooltip rather than silently rounding bookkeeping data.
const formatted = computed(() => {
    let value: string | number;

    if (props.displayAmount !== null && props.displayAmount !== undefined) {
        value = props.displayAmount;
    } else {
        if (resolved.value === undefined || props.rates === null) {
            return undefined;
        }

        value = formatMoney(
            resolved.value,
            props.currency,
            props.displayCurrency,
            props.rates,
        );
    }

    return props.showCurrency
        ? formatCurrencyDisplay(value, props.displayCurrency)
        : formatCurrencyNumber(value, props.displayCurrency);
});
const isNegative = computed(() => formatted.value?.startsWith('(') ?? false);
</script>

<template>
    <span
        v-if="formatted !== undefined"
        :class="[
            'transition-[filter] duration-150',
            isNegative ? '!text-[#E94E50]' : '',
        ]"
        :aria-label="masked ? 'Amount hidden' : undefined"
        >{{ masked ? '••••••' : formatted }}</span
    >
    <span
        v-else
        aria-hidden="true"
        class="inline-block h-[1em] w-16 animate-pulse rounded bg-white/10 align-middle"
    />
</template>
