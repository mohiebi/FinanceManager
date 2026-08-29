/**
 * The Intl locale tag for the app's own locale code.
 *
 * Only Persian needs the region qualifier — it's what makes
 * `Intl.NumberFormat`/`Intl.DateTimeFormat` pick Persian digits and
 * separators instead of Latin ones. Mirrors the inline mapping already used
 * around the app (Landing.vue, settings/AiConnections.vue).
 */
export function intlLocale(locale: string): string {
    return locale === 'fa' ? 'fa-IR' : locale;
}

/**
 * A plain count, index, or calendar year, rendered in the viewer's own digits
 * — Persian for `fa`, unchanged for everyone else. For values that go through
 * vue-i18n's `t()` interpolation, which stringifies numbers as-is rather than
 * localising them the way its own `n()` does.
 *
 * Grouping is deliberately off: these values were a bare digit string before
 * (`String(2026)`, not `"2,026"`), and a year is never grouped regardless —
 * only the digit glyphs should change, not the shape of the number.
 */
export function localizeDigits(value: number, locale: string): string {
    return new Intl.NumberFormat(intlLocale(locale), {
        useGrouping: false,
    }).format(value);
}
