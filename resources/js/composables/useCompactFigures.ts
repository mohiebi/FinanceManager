import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';

export type UseCompactFiguresReturn = {
    compact: ComputedRef<boolean>;
};

/**
 * Whether amounts should round to something scannable at a glance
 * (988.7M instead of 988,691,514) — a single account-wide preference, unlike
 * the amount mask, which also has a per-device override via the header. There
 * is no equivalent quick toggle here, so this only ever reads the shared
 * Inertia prop rather than keeping its own local/localStorage state.
 */
export function useCompactFigures(): UseCompactFiguresReturn {
    const page = usePage();

    const compact = computed(() => page.props.compactFiguresEnabled === true);

    return { compact };
}
