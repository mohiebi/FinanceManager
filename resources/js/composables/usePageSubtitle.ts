import type { InjectionKey, Ref } from 'vue';
import { inject, onUnmounted, provide, ref, watchEffect } from 'vue';

export const pageSubtitleKey: InjectionKey<Ref<string | null>> =
    Symbol('pageSubtitle');

/**
 * Creates the shared subtitle ref and provides it to descendants — call once,
 * from the layout that also renders the header the subtitle appears in.
 */
export function providePageSubtitle(): Ref<string | null> {
    const subtitle = ref<string | null>(null);
    provide(pageSubtitleKey, subtitle);

    return subtitle;
}

/**
 * Publishes this page's header subtitle, matching the mock's
 * "Dashboard · 5 days left" pattern under the page title. `source` is
 * re-read whenever its dependencies change; the subtitle is cleared on
 * unmount so it never bleeds into a page that doesn't set one of its own.
 *
 * A no-op outside `providePageSubtitle()` (e.g. component unit tests), so
 * pages can call this unconditionally.
 */
export function usePageSubtitle(source: () => string | null): void {
    const subtitle = inject(pageSubtitleKey, null);

    if (!subtitle) {
        return;
    }

    watchEffect(() => {
        subtitle.value = source();
    });

    onUnmounted(() => {
        subtitle.value = null;
    });
}
