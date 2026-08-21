import { usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import type { Ref } from 'vue';

/**
 * A privacy blur for on-screen amounts — independent of the vault. The vault
 * decides whether a value is decrypted at all; this only decides whether an
 * already-resolved value is blurred from a shoulder-surfer.
 */
const STORAGE_KEY_PREFIX = 'cashpilot:amount-mask';
const browserMasked = ref(false);
let activeBrowserStorageKey: string | null = null;

type InertiaPage = ReturnType<typeof usePage>;

function amountMaskDefault(page: InertiaPage): boolean {
    return page.props?.amountMaskDefault === true;
}

function storageKey(page: InertiaPage): string {
    const userId = page.props?.auth.user?.id;

    return STORAGE_KEY_PREFIX + ':' + (userId ?? 'guest');
}

function readBrowserInitial(page: InertiaPage, key: string): boolean {
    try {
        const stored = window.localStorage.getItem(key);

        return stored === null ? amountMaskDefault(page) : stored === '1';
    } catch {
        // Storage can be unavailable (private mode, disabled) — the account
        // default is still a real answer, so use it rather than "unmasked".
        return amountMaskDefault(page);
    }
}

function persistBrowserValue(key: string, value: boolean): void {
    try {
        window.localStorage.setItem(key, value ? '1' : '0');
    } catch {
        // Nothing to fall back to — the toggle still works for this tab.
    }
}

function createControls(
    masked: Ref<boolean>,
    persist?: (value: boolean) => void,
): UseAmountMaskReturn {
    function setMasked(value: boolean): void {
        masked.value = value;
        persist?.(value);
    }

    function toggle(): void {
        setMasked(!masked.value);
    }

    return { masked, toggle, setMasked };
}

export type UseAmountMaskReturn = {
    masked: Ref<boolean>;
    toggle: () => void;
    setMasked: (value: boolean) => void;
};

export function useAmountMask(): UseAmountMaskReturn {
    // `usePage()` must run during component setup. Calling it at module scope
    // happens before Inertia installs the current page during SSR.
    const page = usePage();

    if (typeof window === 'undefined') {
        // Keep SSR state request-local. A module-level ref survives between
        // renders in the Node worker and could expose one user's choice to the
        // next request.
        return createControls(ref(amountMaskDefault(page)));
    }

    function syncBrowserUser(): void {
        const key = storageKey(page);

        if (key === activeBrowserStorageKey) {
            return;
        }

        activeBrowserStorageKey = key;
        browserMasked.value = readBrowserInitial(page, key);
    }

    syncBrowserUser();

    watch(
        () => page.props?.auth.user?.id,
        () => syncBrowserUser(),
    );

    return createControls(browserMasked, (value) => {
        persistBrowserValue(storageKey(page), value);
    });
}
