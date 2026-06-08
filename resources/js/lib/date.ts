import { toJalaali } from 'jalaali-js';

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

    const jalali = toJalaali(date.getFullYear(), date.getMonth() + 1, date.getDate());

    return `${jalaliMonthAbbreviations[jalali.jm - 1]} ${jalali.jd}`;
}

export function monthBucketKeyFromIso(value: string, calendar: string | undefined): string {
    const [year, month] = value.split('-').map(Number);

    if (calendar === 'jalali') {
        const jalali = toJalaali(year, month, 1);
        return `${jalali.jy}-${pad(jalali.jm)}`;
    }

    return `${year}-${pad(month)}`;
}

export type MonthBucket = { key: string; label: string };

export function recentMonthBuckets(
    count: number,
    calendar: string | undefined,
    monthLabelLocale: string,
): MonthBucket[] {
    const now = new Date();

    if (calendar === 'jalali') {
        const today = toJalaali(now.getFullYear(), now.getMonth() + 1, now.getDate());
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
        const monthDate = new Date(now.getFullYear(), now.getMonth() - (count - 1 - index), 1);

        return {
            key: `${monthDate.getFullYear()}-${pad(monthDate.getMonth() + 1)}`,
            label: monthDate.toLocaleDateString(monthLabelLocale, { month: 'short' }),
        };
    });
}
