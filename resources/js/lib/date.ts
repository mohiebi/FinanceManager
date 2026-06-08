import { toJalaali } from 'jalaali-js';

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

const jalaliMonthAbbreviations = [
    'فرو',
    'ارد',
    'خرد',
    'تیر',
    'مرد',
    'شهر',
    'مهر',
    'آبا',
    'آذر',
    'دی',
    'بهم',
    'اسف',
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
