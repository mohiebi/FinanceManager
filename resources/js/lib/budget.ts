/**
 * Budget allowances, ported from App\Support\BudgetMath.
 *
 * Under the vault the income, the fixed amounts and the spending are all
 * ciphertext, so the server cannot resolve a plan and the browser does it
 * instead. Two implementations therefore exist;
 * tests/fixtures/budget-vectors.json is the only thing keeping them honest —
 * see tests/js/budget.test.ts and BudgetMathTest.php.
 *
 * Dates are deliberately absent: period boundaries are never encrypted, so the
 * server always resolves them and no calendar maths is duplicated here.
 */
const MONEY_PRECISION = 2;
const RATIO_PRECISION = 6;

export type BudgetRule = 'percent' | 'fixed' | 'remainder';

export type BudgetMathLine = {
    rule: BudgetRule;
    percent?: number;
    fixed?: number;
    actual?: number;
};

export type BudgetLineAllowance = {
    allocated: number;
    actual: number;
    remaining: number;
    progress: number | null;
    share: number | null;
    over: boolean;
};

export type BudgetAllowances = {
    income: number;
    allocated: number;
    unallocated: number;
    over_allocated: number;
    actual: number;
    lines: BudgetLineAllowance[];
};

function money(value: number): number {
    return Number(value.toFixed(MONEY_PRECISION));
}

function ratio(value: number): number {
    return Number(value.toFixed(RATIO_PRECISION));
}

export function computeAllowances(
    income: number,
    lines: BudgetMathLine[],
): BudgetAllowances {
    let claimed = 0;

    // Remainder lines cannot be priced until every other line has taken its
    // share, so they are left null and filled in below.
    const allowances = lines.map((line) => {
        const allowance =
            line.rule === 'percent'
                ? (income * (line.percent ?? 0)) / 100
                : line.rule === 'fixed'
                  ? (line.fixed ?? 0)
                  : null;

        if (allowance !== null) {
            claimed += allowance;
        }

        return allowance;
    });

    const remainderCount = allowances.filter(
        (allowance) => allowance === null,
    ).length;

    // Clamped at zero: a plan that already promises more than it earns has
    // nothing left over, and a negative allowance is not a thing a user can
    // spend. The shortfall surfaces as `over_allocated` instead.
    const pool = Math.max(0, income - claimed);
    const perRemainder = remainderCount > 0 ? pool / remainderCount : 0;

    let allocatedTotal = 0;
    let actualTotal = 0;

    const resolved = lines.map((line, index) => {
        const allocated = allowances[index] ?? perRemainder;
        const actual = line.actual ?? 0;

        allocatedTotal += allocated;
        actualTotal += actual;

        return {
            allocated: money(allocated),
            actual: money(actual),
            remaining: money(allocated - actual),
            // Null rather than zero against a zero allowance: "0% of nothing"
            // is not a fact about the user, and a bar cannot render it honestly.
            progress: allocated > 0 ? ratio(actual / allocated) : null,
            share: income > 0 ? ratio(allocated / income) : null,
            over: actual > allocated,
        };
    });

    return {
        income: money(income),
        allocated: money(allocatedTotal),
        unallocated: money(pool - perRemainder * remainderCount),
        // What the fixed and percentage lines promise beyond the income. The one
        // number that says "this plan does not add up".
        over_allocated: money(Math.max(0, claimed - income)),
        actual: money(actualTotal),
        lines: resolved,
    };
}
