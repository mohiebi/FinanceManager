<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Morilog\Jalali\Jalalian;

/**
 * Computes the next due date for a recurring monthly bill, in either the
 * Gregorian or Jalali calendar, clamping to the last day of shorter months
 * (e.g. day 31 in a 29/30-day month).
 */
class BillDueDateCalculator
{
    public function nextOccurrence(int $dueDay, string $calendar, ?Carbon $after = null): Carbon
    {
        $after = ($after ?? Carbon::today())->copy()->startOfDay();

        return $calendar === 'jalali'
            ? $this->nextJalaliOccurrence($dueDay, $after)
            : $this->nextGregorianOccurrence($dueDay, $after);
    }

    private function nextGregorianOccurrence(int $dueDay, Carbon $after): Carbon
    {
        $candidate = $this->clampGregorian($after->year, $after->month, $dueDay);

        if ($candidate->lt($after)) {
            $next = $after->copy()->addMonthNoOverflow()->startOfMonth();
            $candidate = $this->clampGregorian($next->year, $next->month, $dueDay);
        }

        return $candidate;
    }

    private function clampGregorian(int $year, int $month, int $day): Carbon
    {
        $firstOfMonth = Carbon::create($year, $month, 1);
        $daysInMonth = $firstOfMonth->daysInMonth;

        return Carbon::create($year, $month, min($day, $daysInMonth))->startOfDay();
    }

    private function nextJalaliOccurrence(int $dueDay, Carbon $after): Carbon
    {
        $jAfter = Jalalian::fromCarbon($after);
        $candidate = $this->clampJalali($jAfter->getYear(), $jAfter->getMonth(), $dueDay);

        if ($candidate->lt($after)) {
            $jNext = $jAfter->addMonths(1);
            $candidate = $this->clampJalali($jNext->getYear(), $jNext->getMonth(), $dueDay);
        }

        return $candidate;
    }

    private function clampJalali(int $year, int $month, int $day): Carbon
    {
        $firstOfMonth = Jalalian::fromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $year, $month, 1));
        $daysInMonth = $firstOfMonth->getMonthDays();
        $clampedDay = min($day, $daysInMonth);

        $carbon = Jalalian::fromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $year, $month, $clampedDay))
            ->toCarbon();

        return Carbon::instance($carbon)->startOfDay();
    }
}
