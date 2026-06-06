import { computed, ref } from 'vue';

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    document.documentElement.classList.add('dark');
}

export function useAppearance() {
    const appearance = ref('dark');
    const resolvedAppearance = computed(() => 'dark' as const);

    function updateAppearance(_value: string) {}

    return { appearance, resolvedAppearance, updateAppearance };
}
