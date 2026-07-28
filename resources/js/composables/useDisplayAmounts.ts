import { computed, ref, watchEffect } from 'vue';
import type { ComputedRef } from 'vue';
import { useVault } from '@/composables/useVault';
import { convert } from '@/lib/money';
import type { CurrencyCode, Rates } from '@/lib/money';
import type { Encrypted } from '@/types/vault';

/**
 * Anything with a money amount the server may or may not have been able to read.
 */
export type AmountBearing = {
    id: number;
    amount: Encrypted<string | number>;
    currency: CurrencyCode;
    /** Converted server-side, or null when the vault left it unreadable. */
    display_amount?: string | number | null;
};

export type UseDisplayAmountsReturn = {
    /**
     * Every amount converted into the selected currency, keyed by row id.
     *
     * Null until the whole set is resolved. Charts and totals must wait for all of
     * it — a bar chart drawn from half the rows is a wrong chart, not a loading
     * one, and there is nothing on screen to say so.
     */
    amounts: ComputedRef<Map<number, number> | null>;
    ready: ComputedRef<boolean>;
    /** Sum of the listed rows, or null while any of them is still sealed. */
    totalOf: (rows: readonly AmountBearing[]) => number | null;
};

/**
 * Resolve display amounts for a set of rows, whichever side did the maths.
 *
 * With the vault off the server already converted them and this hands them
 * straight back. With it on, the amounts arrive as ciphertext and the conversion
 * happens here, against the public rates shipped alongside — so every caller has
 * one code path instead of branching on vault state per chart.
 */
export function useDisplayAmounts(
    source: () => readonly AmountBearing[],
    target: () => CurrencyCode,
    rates: () => Rates | null,
    table = 'transactions',
): UseDisplayAmountsReturn {
    const { revealAsync, trackKey } = useVault();

    /**
     * The server-converted case, resolved without an async tick.
     *
     * Worth keeping separate: routing it through the async path would flash a
     * skeleton on every page load for the users who have no vault at all.
     */
    const passthrough = computed(() => {
        const rows = source();

        if (
            rows.some(
                (row) =>
                    row.display_amount === null ||
                    row.display_amount === undefined,
            )
        ) {
            return null;
        }

        return new Map(
            rows.map((row) => [row.id, Number(row.display_amount) || 0]),
        );
    });

    const decrypted = ref<Map<number, number> | null>(null);

    watchEffect(async () => {
        // Tracked before any await, so unlocking re-runs this and the totals and
        // charts fill in on the page the user is already on.
        trackKey();

        if (passthrough.value !== null) {
            return;
        }

        const rows = source();
        const currency = target();
        const currentRates = rates();

        const entries = await Promise.all(
            rows.map(async (row): Promise<[number, number] | null> => {
                if (
                    row.display_amount !== null &&
                    row.display_amount !== undefined
                ) {
                    return [row.id, Number(row.display_amount) || 0];
                }

                const amount = await revealAsync<string | number>(
                    row.amount,
                    table,
                    'decimal',
                );

                // No key yet, or no rates to convert with: there is no honest
                // number to show, so the whole set stays pending.
                if (amount === undefined || currentRates === null) {
                    return null;
                }

                return [
                    row.id,
                    convert(amount, row.currency, currency, currentRates),
                ];
            }),
        );

        decrypted.value = entries.some((entry) => entry === null)
            ? null
            : new Map(entries as [number, number][]);
    });

    const amounts = computed(() => passthrough.value ?? decrypted.value);

    return {
        amounts,
        ready: computed(() => amounts.value !== null),
        totalOf: (rows) => {
            const map = amounts.value;

            if (map === null) {
                return null;
            }

            const total = rows.reduce(
                (carry, row) => carry + (map.get(row.id) ?? 0),
                0,
            );

            return Math.round(total * 100) / 100;
        },
    };
}
