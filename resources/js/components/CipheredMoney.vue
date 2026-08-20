<script setup lang="ts">
import { computed, ref, watchEffect } from 'vue';
import { useAmountMask } from '@/composables/useAmountMask';
import { useCompactFigures } from '@/composables/useCompactFigures';
import { useVault } from '@/composables/useVault';
import { format as formatMoney } from '@/lib/money';
import type { CurrencyCode, Rates } from '@/lib/money';
import type { Encrypted } from '@/types/vault';

const props = withDefaults(
    defineProps<{
        amount: Encrypted<string | number> | null | undefined;
        displayAmount?: string | number | null;
        currency: CurrencyCode;
        displayCurrency: CurrencyCode;
        rates: Rates | null;
        /** Table the amount came from — needed to rebuild the AAD. */
        table?: string;
    }>(),
    { table: 'transactions' },
);

const { reveal, revealAsync, trackKey } = useVault();
const { masked } = useAmountMask();
const { compact } = useCompactFigures();

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

// notation:'compact' is what actually produces "988.7M" — everything else
// about the two formatters is identical, so this is the only branch needed.
const numberFormatter = computed(() =>
    compact.value
        ? new Intl.NumberFormat('en-US', {
              notation: 'compact',
              maximumFractionDigits: 1,
          })
        : new Intl.NumberFormat('en-US'),
);

const formatted = computed(() => {
    if (props.displayAmount !== null && props.displayAmount !== undefined) {
        return numberFormatter.value.format(
            Number(String(props.displayAmount).replace(/,/g, '')) || 0,
        );
    }

    if (resolved.value === undefined || props.rates === null) {
        return undefined;
    }

    return numberFormatter.value.format(
        Number(
            formatMoney(
                resolved.value,
                props.currency,
                props.displayCurrency,
                props.rates,
            ),
        ) || 0,
    );
});
</script>

<template>
    <span
        v-if="formatted !== undefined"
        :class="
            masked
                ? 'blur-[6px] transition-[filter] duration-150 select-none'
                : 'transition-[filter] duration-150'
        "
        >{{ formatted }}</span
    >
    <span
        v-else
        aria-hidden="true"
        class="inline-block h-[1em] w-16 animate-pulse rounded bg-white/10 align-middle"
    />
</template>
