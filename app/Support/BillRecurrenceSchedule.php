<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class BillRecurrenceSchedule
{
    public const MAX_OCCURRENCES = 600;

    public function __construct(private readonly BillDueDateCalculator $calculator) {}

    /**
     * Count monthly occurrences on or after the search date and no later than
     * the selected end date. One extra result is allowed so callers can detect
     * a plan beyond the supported maximum without iterating indefinitely.
     */
    public function countThrough(int $dueDay, string $calendar, Carbon $searchFrom, Carbon $endDate): int
    {
        $count = 0;
        $after = $searchFrom->copy()->startOfDay();
        $endDate = $endDate->copy()->startOfDay();

        while ($count <= self::MAX_OCCURRENCES) {
            $dueDate = $this->calculator->nextOccurrence($dueDay, $calendar, $after);

            if ($dueDate->gt($endDate)) {
                break;
            }

            $count++;
            $after = $dueDate->copy()->addDay();
        }

        return $count;
    }
}
