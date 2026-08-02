<?php

namespace App\Support;

use App\Enums\BudgetRuleType;

/**
 * Turns a set of budget rules and an income into per-line allowances.
 *
 * Split out from everything else for the same reason {@see GoalPace} is: under
 * the vault the income, the fixed amounts and the spending are all ciphertext,
 * so the server cannot do this and the browser has to. Numbers in, numbers out —
 * no Carbon, no calendar, nothing a Jalali conversion could disagree about.
 *
 * Mirrored by resources/js/lib/budget.ts and pinned by
 * tests/fixtures/budget-vectors.json.
 */
class BudgetMath
{
    /** Money rounds to 2dp, matching how every amount is stored. */
    public const MONEY_PRECISION = 2;

    /** Ratios get more room — 2dp would make 33.3% of a plan read as 0.33. */
    public const RATIO_PRECISION = 6;

    /**
     * @param  float  $income  the period's income, on whichever basis the budget uses
     * @param  array<int, array{rule: string, percent?: float, fixed?: float, actual?: float}>  $lines
     * @return array{
     *     income: float,
     *     allocated: float,
     *     unallocated: float,
     *     over_allocated: float,
     *     actual: float,
     *     lines: array<int, array{
     *         allocated: float,
     *         actual: float,
     *         remaining: float,
     *         progress: float|null,
     *         share: float|null,
     *         over: bool,
     *     }>,
     * }
     */
    public static function compute(float $income, array $lines): array
    {
        $claimed = 0.0;
        $allowances = [];

        foreach ($lines as $index => $line) {
            $rule = $line['rule'] ?? BudgetRuleType::Fixed->value;

            // Remainder lines cannot be priced until every other line has taken
            // its share, so they are left null and filled in below.
            $allowance = match ($rule) {
                BudgetRuleType::Percent->value => $income * (float) ($line['percent'] ?? 0) / 100,
                BudgetRuleType::Fixed->value => (float) ($line['fixed'] ?? 0),
                default => null,
            };

            $allowances[$index] = $allowance;

            if ($allowance !== null) {
                $claimed += $allowance;
            }
        }

        $remainderCount = count(array_filter(
            $allowances,
            static fn (?float $allowance): bool => $allowance === null,
        ));

        // Clamped at zero: a plan that already promises more than it earns has
        // nothing left over, and a negative allowance is not a thing a user can
        // spend. The shortfall surfaces as `over_allocated` instead.
        $pool = max(0.0, $income - $claimed);
        $perRemainder = $remainderCount > 0 ? $pool / $remainderCount : 0.0;

        $allocatedTotal = 0.0;
        $actualTotal = 0.0;
        $resolved = [];

        foreach ($lines as $index => $line) {
            $allocated = $allowances[$index] ?? $perRemainder;
            $actual = (float) ($line['actual'] ?? 0);

            $allocatedTotal += $allocated;
            $actualTotal += $actual;

            $resolved[] = [
                'allocated' => self::money($allocated),
                'actual' => self::money($actual),
                'remaining' => self::money($allocated - $actual),
                // Null rather than zero against a zero allowance: "0% of nothing"
                // is not a fact about the user, and a bar cannot render it honestly.
                'progress' => $allocated > 0 ? self::ratio($actual / $allocated) : null,
                'share' => $income > 0 ? self::ratio($allocated / $income) : null,
                'over' => $actual > $allocated,
            ];
        }

        return [
            'income' => self::money($income),
            'allocated' => self::money($allocatedTotal),
            'unallocated' => self::money($pool - ($perRemainder * $remainderCount)),
            // What the fixed and percentage lines promise beyond the income. The
            // one number that says "this plan does not add up".
            'over_allocated' => self::money(max(0.0, $claimed - $income)),
            'actual' => self::money($actualTotal),
            'lines' => $resolved,
        ];
    }

    private static function money(float $value): float
    {
        return round($value, self::MONEY_PRECISION);
    }

    private static function ratio(float $value): float
    {
        return round($value, self::RATIO_PRECISION);
    }
}
