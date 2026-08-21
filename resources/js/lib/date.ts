import jalaali from 'jalaali-js';

// jalaali-js ships CommonJS, so named imports resolve only through a
// bundler. Destructuring the default export works in both, which keeps
// this module importable by the plain node test runner.
const { jalaaliMonthLength, toGregorian, toJalaali } = jalaali;

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

export type CalendarPreference = 'gregorian' | 'jalali';

export function formatAppDate(
    value: string | null | undefined,
    calendar: string | undefined,
): string {
    if (!value) {
        return '';
    }

    if (calendar !== 'jalali') {
        return value;
    }

    const [year, month, day] = value.split('-').map(Number);

    if (!year || !month || !day) {
        return value;
    }

    const jalali = toJalaali(year, month, day);

    return `${jalali.jy}-${String(jalali.jm).padStart(2, '0')}-${String(jalali.jd).padStart(2, '0')}`;
}

export const jalaliMonthAbbreviations = [
    'فرورودین',
    'اردیبهشت',
    'خرداد',
    'تیر',
    'مرداد',
    'شهریور',
    'مهر',
    'آبان',
    'آذر',
    'دی',
    'بهمن',
    'اسفند',
];

export function formatChartDateLabel(
    value: string,
    calendar: string | undefined,
): string {
    const date = new Date(value);

    if (isNaN(date.getTime())) {
        return value;
    }

    if (calendar !== 'jalali') {
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
        });
    }

    const jalali = toJalaali(
        date.getFullYear(),
        date.getMonth() + 1,
        date.getDate(),
    );

    return `${jalaliMonthAbbreviations[jalali.jm - 1]} ${jalali.jd}`;
}

export function monthBucketKeyFromIso(
    value: string,
    calendar: string | undefined,
): string {
    const [year, month, day] = value.split('-').map(Number);

    if (calendar === 'jalali') {
        if (!year || !month || !day) {
            return value;
        }

        const jalali = toJalaali(year, month, day);

        return `${jalali.jy}-${pad(jalali.jm)}`;
    }

    return `${year}-${pad(month)}`;
}

export type MonthBucket = { key: string; label: string };

/**
 * Month buckets (calendar-aware) covering an arbitrary inclusive ISO date range.
 */
export function monthBucketsBetween(
    fromIso: string,
    toIso: string,
    calendar: string | undefined,
    monthLabelLocale: string,
): MonthBucket[] {
    const from = new Date(`${fromIso.slice(0, 10)}T00:00:00`);
    const to = new Date(`${toIso.slice(0, 10)}T00:00:00`);

    if (isNaN(from.getTime()) || isNaN(to.getTime()) || from > to) {
        return [];
    }

    const buckets: MonthBucket[] = [];

    if (calendar === 'jalali') {
        const start = toJalaali(
            from.getFullYear(),
            from.getMonth() + 1,
            from.getDate(),
        );
        const end = toJalaali(
            to.getFullYear(),
            to.getMonth() + 1,
            to.getDate(),
        );

        let jy = start.jy;
        let jm = start.jm;

        while (jy < end.jy || (jy === end.jy && jm <= end.jm)) {
            buckets.push({
                key: `${jy}-${pad(jm)}`,
                label: jalaliMonthAbbreviations[jm - 1],
            });

            jm += 1;

            if (jm > 12) {
                jm = 1;
                jy += 1;
            }
        }

        return buckets;
    }

    const cursor = new Date(from.getFullYear(), from.getMonth(), 1);
    const endMonth = new Date(to.getFullYear(), to.getMonth(), 1);

    while (cursor <= endMonth) {
        buckets.push({
            key: `${cursor.getFullYear()}-${pad(cursor.getMonth() + 1)}`,
            label: cursor.toLocaleDateString(monthLabelLocale, {
                month: 'short',
            }),
        });

        cursor.setMonth(cursor.getMonth() + 1);
    }

    return buckets;
}

/**
 * Inclusive list of ISO dates between two ISO dates (capped for safety).
 */
export function dayBucketsBetween(
    fromIso: string,
    toIso: string,
    maxDays = 92,
): string[] {
    const from = new Date(`${fromIso.slice(0, 10)}T00:00:00`);
    const to = new Date(`${toIso.slice(0, 10)}T00:00:00`);

    if (isNaN(from.getTime()) || isNaN(to.getTime()) || from > to) {
        return [];
    }

    const days: string[] = [];
    const cursor = new Date(from);

    while (cursor <= to && days.length < maxDays) {
        days.push(
            `${cursor.getFullYear()}-${pad(cursor.getMonth() + 1)}-${pad(cursor.getDate())}`,
        );

        cursor.setDate(cursor.getDate() + 1);
    }

    return days;
}

export function recentMonthBuckets(
    count: number,
    calendar: string | undefined,
    monthLabelLocale: string,
): MonthBucket[] {
    const now = new Date();

    if (calendar === 'jalali') {
        const today = toJalaali(
            now.getFullYear(),
            now.getMonth() + 1,
            now.getDate(),
        );
        const buckets: MonthBucket[] = [];

        for (let i = count - 1; i >= 0; i--) {
            let jy = today.jy;
            let jm = today.jm - i;

            while (jm <= 0) {
                jm += 12;
                jy -= 1;
            }

            buckets.push({
                key: `${jy}-${pad(jm)}`,
                label: jalaliMonthAbbreviations[jm - 1],
            });
        }

        return buckets;
    }

    return Array.from({ length: count }, (_, index) => {
        const monthDate = new Date(
            now.getFullYear(),
            now.getMonth() - (count - 1 - index),
            1,
        );

        return {
            key: `${monthDate.getFullYear()}-${pad(monthDate.getMonth() + 1)}`,
            label: monthDate.toLocaleDateString(monthLabelLocale, {
                month: 'short',
            }),
        };
    });
}

/** Saturday-first weekday abbreviations, matching how the Jalali calendar is
 *  conventionally laid out (ش ی د س چ پ ج = Sat..Fri). */
const jalaliWeekdayLabels = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

export type CalendarDayCell = {
    /** The underlying Gregorian ISO date — how due dates are matched against it. */
    iso: string;
    /** Day-of-month number to display, in the active calendar. */
    day: number;
    isToday: boolean;
};

export type CalendarMonthGrid = {
    monthLabel: string;
    weekdayLabels: string[];
    /** Always a multiple of 7 cells per row; `null` pads the leading/trailing days. */
    weeks: (CalendarDayCell | null)[][];
};

function chunkIntoWeeks(
    cells: (CalendarDayCell | null)[],
): (CalendarDayCell | null)[][] {
    const weeks: (CalendarDayCell | null)[][] = [];

    for (let i = 0; i < cells.length; i += 7) {
        weeks.push(cells.slice(i, i + 7));
    }

    return weeks;
}

/**
 * Builds the current month's day grid for the Bills calendar view, in
 * whichever calendar system the user has chosen. Cells always carry the
 * Gregorian ISO date underneath, since that's how due dates are stored and
 * sent from the server — only the displayed day number and month label
 * differ by calendar.
 */
export function buildCalendarMonth(
    todayIso: string,
    calendar: string | undefined,
    monthLabelLocale: string,
): CalendarMonthGrid {
    const [ty, tm, td] = todayIso.split('-').map(Number);

    if (!ty || !tm || !td) {
        return { monthLabel: '', weekdayLabels: [], weeks: [] };
    }

    if (calendar === 'jalali') {
        const today = toJalaali(ty, tm, td);
        const daysInMonth = jalaaliMonthLength(today.jy, today.jm);
        const firstGregorian = toGregorian(today.jy, today.jm, 1);
        const firstWeekday = new Date(
            firstGregorian.gy,
            firstGregorian.gm - 1,
            firstGregorian.gd,
        ).getDay();
        // JS getDay() is Sunday-first (0-6); shift so Saturday lands in column 0.
        const firstColumn = (firstWeekday + 1) % 7;

        const cells: (CalendarDayCell | null)[] = Array.from(
            { length: firstColumn },
            () => null,
        );

        for (let day = 1; day <= daysInMonth; day++) {
            const gregorian = toGregorian(today.jy, today.jm, day);
            cells.push({
                iso: `${gregorian.gy}-${pad(gregorian.gm)}-${pad(gregorian.gd)}`,
                day,
                isToday: day === today.jd,
            });
        }

        while (cells.length % 7 !== 0) {
            cells.push(null);
        }

        return {
            monthLabel: `${jalaliMonthAbbreviations[today.jm - 1]} ${today.jy}`,
            weekdayLabels: jalaliWeekdayLabels,
            weeks: chunkIntoWeeks(cells),
        };
    }

    const daysInMonth = new Date(ty, tm, 0).getDate();
    const firstColumn = new Date(ty, tm - 1, 1).getDay();

    const cells: (CalendarDayCell | null)[] = Array.from(
        { length: firstColumn },
        () => null,
    );

    for (let day = 1; day <= daysInMonth; day++) {
        cells.push({
            iso: `${ty}-${pad(tm)}-${pad(day)}`,
            day,
            isToday: day === td,
        });
    }

    while (cells.length % 7 !== 0) {
        cells.push(null);
    }

    // January 1, 2023 was a Sunday, so offsetting from it gives Sun..Sat
    // labels in the viewer's own locale.
    const weekdayLabels = Array.from({ length: 7 }, (_, i) =>
        new Date(2023, 0, 1 + i).toLocaleDateString(monthLabelLocale, {
            weekday: 'narrow',
        }),
    );

    return {
        monthLabel: new Date(ty, tm - 1, 1).toLocaleDateString(
            monthLabelLocale,
            { month: 'long', year: 'numeric' },
        ),
        weekdayLabels,
        weeks: chunkIntoWeeks(cells),
    };
}
