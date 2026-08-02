<?php

namespace App\Enums;

/**
 * How a budget line works out what it is allowed to spend.
 *
 * The percent case is the reason this feature exists: a fixed envelope is
 * meaningless to someone whose income varies month to month, while "half of
 * whatever I earn" holds at any income.
 */
enum BudgetRuleType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
    case Remainder = 'remainder';

    /** Whether this rule carries an amount the server may not be able to read. */
    public function isSealed(): bool
    {
        return $this === self::Fixed;
    }
}
