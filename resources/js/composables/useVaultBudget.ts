import { computed, ref, watchEffect } from 'vue';
import type { ComputedRef } from 'vue';
import { useVault } from '@/composables/useVault';
import { computeAllowances } from '@/lib/budget';
import type { BudgetMathLine, BudgetRule } from '@/lib/budget';
import { convert } from '@/lib/money';
import type { CurrencyCode, Rates } from '@/lib/money';
import type {
    BudgetLineCategory,
    BudgetPeriod,
    BudgetProgress,
} from '@/types/budgets';
import type { Encrypted } from '@/types/vault';

/** One plan line as the armed path sends it: structure readable, amount sealed. */
export type VaultBudgetLine = {
    id: number;
    rule_type: BudgetRule;
    percent: number | null;
    rollover_enabled: boolean;
    category: BudgetLineCategory | null;
    fixed_amount: Encrypted<string | number> | null;
    /** What the sealed amount is written in — the currency itself is not a secret. */
    currency: CurrencyCode;
    /** Categories this line owns; for a remainder line, the ones it excludes. */
    category_ids: number[];
};

/**
 * What BuildBudgetProgress::clientPayload sends when the vault is armed.
 *
 * The period is already resolved — month boundaries are never encrypted, so
 * there is no reason to duplicate Jalali maths in the browser. Everything
 * money-shaped is still sealed.
 */
export type VaultBudgetPayload = {
    id: number;
    title: Encrypted<string> | null;
    income_basis: 'actual' | 'expected';
    currency: CurrencyCode;
    period: BudgetPeriod;
    expected_income: Encrypted<string | number> | null;
    lines: VaultBudgetLine[];
    transactions: {
        id: number;
        type: 'income' | 'cost';
        category_id: number | null;
        amount: Encrypted<string | number>;
        currency: CurrencyCode;
    }[];
    /** Public market prices, so shipping them costs nothing in privacy. */
    rates: Rates;
};

export type UseVaultBudgetReturn = {
    progress: ComputedRef<BudgetProgress | null>;
    /**
     * True while the amounts are still sealed.
     *
     * Distinct from `progress === null`, which is also what an account with no
     * budget produces — telling those apart is the difference between a skeleton
     * that resolves and one that never does.
     */
    decrypting: ComputedRef<boolean>;
};

/**
 * Decrypt the period's amounts and resolve the same allowances the server
 * resolves when it can read them.
 *
 * Every transaction in the period is opened once and converted against the
 * shipped rates, then the plan is applied to the totals — the same order the
 * server works in, so the two paths cannot disagree about rounding.
 */
export function useVaultBudget(
    payload: () => VaultBudgetPayload | null | undefined,
): UseVaultBudgetReturn {
    const { revealAsync, trackKey } = useVault();

    const resolved = ref<{
        income: number;
        spentByCategory: Map<number | null, number>;
        fixedAmounts: (number | null)[];
        expectedIncome: number | null;
        title: string | null;
    } | null>(null);

    watchEffect(async () => {
        // Tracked before any await, so unlocking fills the page in place rather
        // than leaving it pulsing until the next navigation.
        trackKey();

        const current = payload();

        if (current === null || current === undefined) {
            resolved.value = null;

            return;
        }

        const amounts = await Promise.all(
            current.transactions.map(async (transaction) => {
                const amount = await revealAsync<string | number>(
                    transaction.amount,
                    'transactions',
                    'decimal',
                );

                return amount === undefined
                    ? undefined
                    : convert(
                          amount,
                          transaction.currency,
                          current.currency,
                          current.rates,
                      );
            }),
        );

        const fixedAmounts = await Promise.all(
            current.lines.map(async (line) => {
                if (line.fixed_amount === null) {
                    return null;
                }

                const amount = await revealAsync<string | number>(
                    line.fixed_amount,
                    'budget_lines',
                    'decimal',
                );

                // Converted into the plan's currency, mirroring
                // BuildBudgetProgress: a line written in dollars and a plan
                // totalled in toman have to meet somewhere.
                return amount === undefined
                    ? undefined
                    : convert(
                          amount,
                          line.currency,
                          current.currency,
                          current.rates,
                      );
            }),
        );

        const expectedIncome =
            current.expected_income === null
                ? null
                : await revealAsync<string | number>(
                      current.expected_income,
                      'budgets',
                      'decimal',
                  );

        const title = await revealAsync<string>(current.title, 'budgets');

        // A locked vault yields nothing rather than a pile of zeroes — a plan
        // showing a target of 0 is a far worse lie than a skeleton.
        if (
            amounts.some((amount) => amount === undefined) ||
            fixedAmounts.some((amount) => amount === undefined) ||
            (current.expected_income !== null && expectedIncome === undefined)
        ) {
            resolved.value = null;

            return;
        }

        let income = 0;
        const spentByCategory = new Map<number | null, number>();

        current.transactions.forEach((transaction, index) => {
            const amount = amounts[index] as number;

            if (transaction.type === 'income') {
                income += amount;

                return;
            }

            const key = transaction.category_id;
            spentByCategory.set(key, (spentByCategory.get(key) ?? 0) + amount);
        });

        resolved.value = {
            income,
            spentByCategory,
            fixedAmounts: fixedAmounts as (number | null)[],
            expectedIncome:
                expectedIncome === undefined || expectedIncome === null
                    ? null
                    : Number(expectedIncome) || 0,
            title: title ?? null,
        };
    });

    const progress = computed<BudgetProgress | null>(() => {
        const current = payload();
        const opened = resolved.value;

        if (current === null || current === undefined || opened === null) {
            return null;
        }

        const income =
            current.income_basis === 'expected'
                ? (opened.expectedIncome ?? 0)
                : opened.income;

        const claimed = current.lines.flatMap((line) =>
            line.rule_type === 'remainder' ? [] : line.category_ids,
        );

        const mathLines: BudgetMathLine[] = current.lines.map(
            (line, index) => ({
                rule: line.rule_type,
                percent: line.percent ?? 0,
                fixed: opened.fixedAmounts[index] ?? 0,
                actual: spentOn(line, claimed, opened.spentByCategory),
            }),
        );

        const allowances = computeAllowances(income, mathLines);

        return {
            id: current.id,
            title: opened.title,
            income_basis: current.income_basis,
            currency: current.currency,
            period: current.period,
            ...allowances,
            lines: current.lines.map((line, index) => ({
                id: line.id,
                rule_type: line.rule_type,
                percent: line.percent,
                rollover_enabled: line.rollover_enabled,
                category: line.category,
                ...allowances.lines[index],
            })),
        };
    });

    return {
        progress,
        decrypting: computed(() => {
            const current = payload();

            return (
                current !== null &&
                current !== undefined &&
                progress.value === null
            );
        }),
    };
}

/**
 * Mirrors BuildBudgetProgress::spentOn.
 *
 * A category line owns every id the server resolved for it — a parent's own
 * plus its unclaimed children — so all of them are summed, not just the first.
 * A remainder line takes every cost no other line claimed, including rows with
 * no category at all — which is why the map is keyed by `number | null`.
 */
function spentOn(
    line: VaultBudgetLine,
    claimed: number[],
    spentByCategory: Map<number | null, number>,
): number {
    if (line.rule_type !== 'remainder') {
        return line.category_ids.reduce(
            (total, categoryId) =>
                total + (spentByCategory.get(categoryId) ?? 0),
            0,
        );
    }

    let total = 0;

    spentByCategory.forEach((amount, categoryId) => {
        if (categoryId === null || !claimed.includes(categoryId)) {
            total += amount;
        }
    });

    return total;
}
