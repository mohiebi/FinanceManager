<script setup lang="ts">
import { computed, ref, watchEffect } from 'vue';
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

const { reveal, revealAsync } = useVault();

const resolved = ref<string | number | undefined>(reveal(props.amount));

watchEffect(async () => {
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

const formatted = computed(() => {
    if (props.displayAmount !== null && props.displayAmount !== undefined) {
        return new Intl.NumberFormat('en-US').format(
            Number(String(props.displayAmount).replace(/,/g, '')) || 0,
        );
    }

    if (resolved.value === undefined || props.rates === null) {
        return undefined;
    }

    return new Intl.NumberFormat('en-US').format(
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
    <span v-if="formatted !== undefined">{{ formatted }}</span>
    <span
        v-else
        aria-hidden="true"
        class="inline-block h-[1em] w-16 animate-pulse rounded bg-white/10 align-middle"
    />
</template>
