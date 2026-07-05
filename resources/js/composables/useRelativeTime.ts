import { onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

export type UseRelativeTimeReturn = {
    formatRelativeTime: (iso?: string | null) => string;
};

const DIVISIONS: { amount: number; unit: Intl.RelativeTimeFormatUnit }[] = [
    { amount: 60, unit: 'second' },
    { amount: 60, unit: 'minute' },
    { amount: 24, unit: 'hour' },
    { amount: 7, unit: 'day' },
    { amount: 4.34524, unit: 'week' },
    { amount: 12, unit: 'month' },
    { amount: Infinity, unit: 'year' },
];

export function useRelativeTime(
    tickIntervalMs = 30_000,
): UseRelativeTimeReturn {
    const { locale } = useI18n();
    const now = ref(Date.now());

    const timer = setInterval(() => {
        now.value = Date.now();
    }, tickIntervalMs);

    onUnmounted(() => clearInterval(timer));

    const formatRelativeTime = (iso?: string | null): string => {
        if (!iso) {
            return '';
        }

        const then = new Date(iso).getTime();
        let duration = (then - now.value) / 1000;

        const rtf = new Intl.RelativeTimeFormat(locale.value, {
            numeric: 'auto',
        });

        for (const division of DIVISIONS) {
            if (Math.abs(duration) < division.amount) {
                return rtf.format(Math.round(duration), division.unit);
            }

            duration /= division.amount;
        }

        return '';
    };

    return { formatRelativeTime };
}
