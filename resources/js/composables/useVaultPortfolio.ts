import { computed, ref, watchEffect } from 'vue';
import type { ComputedRef } from 'vue';
import { useVault } from '@/composables/useVault';
import type { CurrencyCode, Rates } from '@/lib/money';
import { buildBreakdown, buildSnapshot } from '@/lib/portfolio';
import type {
    PortfolioAssetMeta,
    PortfolioBreakdown,
    PortfolioEntry,
    PortfolioSnapshot,
} from '@/lib/portfolio';
import type { Encrypted } from '@/types/vault';

/**
 * What BuildPortfolioBreakdown::clientPayload sends when the vault is armed:
 * holdings the server cannot read, plus the public prices needed to value them.
 */
export type VaultPortfolioPayload = {
    entries: {
        id: number;
        investment_asset_id: number;
        quantity: Encrypted<string | number>;
        cost_basis: Encrypted<string | number> | null;
        cost_basis_currency: string | null;
    }[];
    assets: PortfolioAssetMeta[];
    rates: Rates;
};

export type UseVaultPortfolioReturn = {
    breakdown: ComputedRef<PortfolioBreakdown | null>;
    snapshot: ComputedRef<PortfolioSnapshot | null>;
};

/**
 * Decrypt the holdings and build the same breakdown the server builds when it can
 * read them.
 *
 * Deliberately keyed off the payload being present rather than off the vault
 * being armed: the server only sends it in the armed case, so a page can hand
 * this `undefined` unconditionally and get an inert result.
 */
export function useVaultPortfolio(
    payload: () => VaultPortfolioPayload | null | undefined,
    target: () => CurrencyCode,
): UseVaultPortfolioReturn {
    const { revealAsync } = useVault();

    const decrypted = ref<PortfolioEntry[] | null>(null);

    watchEffect(async () => {
        const current = payload();

        if (current === null || current === undefined) {
            decrypted.value = null;

            return;
        }

        const entries = await Promise.all(
            current.entries.map(async (entry) => {
                const quantity = await revealAsync<string | number>(
                    entry.quantity,
                    'investments',
                    'decimal',
                );
                const costBasis = await revealAsync<string | number>(
                    entry.cost_basis,
                    'investments',
                    'decimal',
                );

                return {
                    investment_asset_id: entry.investment_asset_id,
                    quantity,
                    cost_basis:
                        costBasis === undefined ? null : Number(costBasis) || 0,
                    cost_basis_currency: entry.cost_basis_currency,
                };
            }),
        );

        // A locked vault yields nothing rather than a pile of zeroes — a net worth
        // of zero is a far worse lie than a skeleton that never resolves.
        if (entries.some((entry) => entry.quantity === undefined)) {
            decrypted.value = null;

            return;
        }

        decrypted.value = entries.map((entry) => ({
            ...entry,
            quantity: Number(entry.quantity) || 0,
        }));
    });

    const breakdown = computed<PortfolioBreakdown | null>(() => {
        const current = payload();

        if (current === null || current === undefined) {
            return null;
        }

        if (decrypted.value === null) {
            return null;
        }

        return buildBreakdown(
            decrypted.value,
            current.assets,
            target(),
            current.rates,
        );
    });

    return {
        breakdown,
        snapshot: computed(() =>
            breakdown.value === null ? null : buildSnapshot(breakdown.value),
        ),
    };
}
