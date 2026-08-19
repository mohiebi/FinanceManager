import { ref, watch } from 'vue';

/**
 * A privacy blur for on-screen amounts — independent of the vault. The vault
 * decides whether a value is decrypted at all; this only decides whether an
 * already-resolved value is blurred from a shoulder-surfer. Module-level state
 * so every `CipheredMoney` on the page (and the header toggle that flips it)
 * shares one switch instead of drifting per-component.
 */
const STORAGE_KEY = 'cashpilot:amount-mask';

function readInitial(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    try {
        return window.localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        // Storage can be unavailable (private mode, disabled) — default open.
        return false;
    }
}

const masked = ref(readInitial());

watch(masked, (value) => {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(STORAGE_KEY, value ? '1' : '0');
    } catch {
        // Nothing to fall back to — the toggle still works for this tab.
    }
});

export type UseAmountMaskReturn = {
    masked: typeof masked;
    toggle: () => void;
};

export function useAmountMask(): UseAmountMaskReturn {
    function toggle() {
        masked.value = !masked.value;
    }

    return { masked, toggle };
}
