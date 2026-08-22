import { usePage } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
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

    /*
     * The first client render has to paint what the server painted.
     *
     * The server only knows the account default — it cannot read this device's
     * localStorage — so a client that consults storage during setup disagrees
     * with the server whenever the two differ, and disagrees on the class and
     * the text of every amount on the page. On the dashboard that is most of
     * the page, and a mismatch that large is how hydration ends up adopting a
     * tree Vue then cannot patch cleanly.
     *
     * So the first paint uses the account default, matching the server, and the
     * stored per-device choice is adopted on mount — by which point hydration
     * is done and switching it is an ordinary reactive update.
     */
    if (activeBrowserStorageKey === null) {
        browserMasked.value = amountMaskDefault(page);
    }

    function syncBrowserUser(): void {
        const key = storageKey(page);

        if (key === activeBrowserStorageKey) {
            return;
        }

        activeBrowserStorageKey = key;
        browserMasked.value = readBrowserInitial(page, key);
    }

    onMounted(syncBrowserUser);

    watch(
        () => page.props?.auth.user?.id,
        () => syncBrowserUser(),
    );

    return createControls(browserMasked, (value) => {
        persistBrowserValue(storageKey(page), value);
    });
}
