import type { BudgetRule } from '@/lib/budget';
import type { CurrencyCode } from '@/lib/money';
import type { Encrypted } from '@/types/vault';

export type BudgetIncomeBasis = 'actual' | 'expected';

/**
 * The current period, always resolved server-side.
 *
 * Month boundaries are never encrypted, and a Jalali month recomputed in the
 * browser is how "this month" quietly becomes a different month for half the
 * users.
 */
export type BudgetPeriod = {
    start: string;
    end: string;
    month_end: string;
    /** The month's own name, in the user's calendar. */
    label: string;
    day_of_month: number;
    days_in_month: number;
    days_remaining: number;
};

export type BudgetLineCategory = {
    id: number;
    name: string;
    slug: string;
};

export type BudgetLineProgress = {
    id: number;
    rule_type: BudgetRule;
    percent: number | null;
    rollover_enabled: boolean;
    category: BudgetLineCategory | null;
    allocated: number;
    actual: number;
    remaining: number;
    progress: number | null;
    share: number | null;
    over: boolean;
};

/**
 * A resolved plan, whichever side did the maths.
 *
 * The server builds this from plaintext; useVaultBudget builds the identical
 * shape in the browser when the amounts are sealed, so the page has one code
 * path instead of branching on vault state per row.
 */
export type BudgetProgress = {
    id: number;
    title: string | null;
    income_basis: BudgetIncomeBasis;
    currency: CurrencyCode;
    period: BudgetPeriod;
    income: number;
    allocated: number;
    unallocated: number;
    over_allocated: number;
    actual: number;
    lines: BudgetLineProgress[];
};

/** The plan itself, as the editor works on it. Amounts are sealed under the vault. */
export type BudgetFormLine = {
    id: number | null;
    category_id: number | null;
    rule_type: BudgetRule;
    percent: number | null;
    fixed_amount: Encrypted<string | number> | null;
    /** What a fixed amount is written in. Resolved to the budget's when unset. */
    currency: CurrencyCode;
    rollover_enabled: boolean;
};

export type BudgetForm = {
    id: number;
    title: Encrypted<string> | null;
    income_basis: BudgetIncomeBasis;
    expected_income: Encrypted<string | number> | null;
    currency: CurrencyCode;
    is_active: boolean;
    lines: BudgetFormLine[];
};
