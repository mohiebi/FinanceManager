export type DisplayNumber = string | number;

export function numericValue(value: DisplayNumber): number {
    const normalized = String(value)
        .replace(/,/g, '')
        .replace(/\u2212/g, '-')
        .trim();

    const numeric = Number(normalized);

    return Number.isFinite(numeric) ? numeric : 0;
}

export function formatFullNumber(
    value: DisplayNumber,
    locale: string = 'en-US',
): string {
    const numeric = numericValue(value);

    return new Intl.NumberFormat(locale, {
        maximumFractionDigits: 2,
        minimumFractionDigits: numeric % 1 === 0 ? 0 : 2,
    }).format(numeric);
}

export function formatCompactNumber(
    value: DisplayNumber,
    locale: string = 'en-US',
): string {
    return new Intl.NumberFormat(locale, {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(numericValue(value));
}
