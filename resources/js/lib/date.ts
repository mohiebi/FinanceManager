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
