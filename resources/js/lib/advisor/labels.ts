import { useI18n } from 'vue-i18n';

/**
 * Readable names for the Advisor's enum values.
 *
 * These values — `5_10_years`, `within_week`, `private_asset`, `speculative` —
 * were being rendered by swapping underscores for spaces, which left English
 * identifiers sitting in the middle of Persian and German pages. They also
 * describe the highest-stakes fields in the assessment: risk band and liquidity
 * drive the whole recommendation, so a user has to be able to read them.
 *
 * The humanized fallback only covers a value the locale files have not caught up
 * with yet; TranslationPlaceholderTest keeps the three locales in step.
 */
export function useAdvisorLabels() {
    const { t, te } = useI18n();

    function label(group: string, value: string | null | undefined): string {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        const key = `advisor.${group}.${value}`;

        return te(key) ? t(key) : humanize(value);
    }

    return { label };
}

/** Last resort for a value with no translation yet: at least stop shouting. */
function humanize(value: string): string {
    const spaced = value.replaceAll('_', ' ');

    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}
