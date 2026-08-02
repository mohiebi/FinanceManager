<?php

namespace App\Enums;

/**
 * What a percentage line takes its percentage of.
 *
 * `Actual` is the honest default for irregular income — the target grows as
 * money actually arrives — at the cost of reading zero on the first of the
 * month. `Expected` trades that for a figure the user declares up front, so
 * every target exists from day one and drift shows up as income lands.
 */
enum BudgetIncomeBasis: string
{
    case Actual = 'actual';
    case Expected = 'expected';
}
