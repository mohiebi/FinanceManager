import jalaali from 'jalaali-js';
import { jalaliMonthAbbreviations } from '../date.ts';

const { toJalaali } = jalaali;

/**
 * A date as the Advisor's mono bands print it — `21 AUG 2026`.
 *
 * Uppercase because every band on the dossier and the recommendation header is,
 * and in the user's own calendar because a Jalali user reading "21 AUG 2026" on
 * a sealed document has to convert it in their head to know when they sealed it.
 */
export function sealDate(
    value: string | null | undefined,
    calendar: string | undefined,
): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    if (calendar === 'jalali') {
        const jalali = toJalaali(
            date.getFullYear(),
            date.getMonth() + 1,
            date.getDate(),
        );

        return `${jalali.jd} ${jalaliMonthAbbreviations[jalali.jm - 1]} ${jalali.jy}`;
    }

    return date
        .toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        })
        .toUpperCase();
}

/** The same band, with the time appended — used by the recommendation header. */
export function sealDateTime(
    value: string | null | undefined,
    calendar: string | undefined,
): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    const time = date.toLocaleTimeString('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
    });

    return `${sealDate(value, calendar)}, ${time}`;
}

/** `No. 0002` — the dossier's reference number, always four digits. */
export function documentNumber(id: number | string): string {
    return String(id).padStart(4, '0');
}
