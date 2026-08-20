import { usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

/**
 * A privacy blur for on-screen amounts — independent of the vault. The vault
 * decides whether a value is decrypted at all; this only decides whether an
 * already-resolved value is blurred from a shoulder-surfer. Module-level state
 * so every `CipheredMoney` on the page (and the header toggle that flips it)
 * shares one switch instead of drifting per-component.
 */
const STORAGE_KEY_PREFIX = 'cashpilot:amount-mask';
const page = usePage();

function storageKey(): string {
    const userId = page.props.auth.user?.id;

    return STORAGE_KEY_PREFIX + ':' + (userId ?? 'guest');
}

/**
 * localStorage wins when this device already has an explicit choice — that
 * per-device override is the whole point of storing it there. Only a device
 * that has never touched the toggle (a fresh browser, a new phone) falls back
 * to `amountMaskDefault`, the account-wide default set from Settings > Money >
 * Privacy, instead of hard-coding "unmasked".
 */
function readInitial(): boolean {
    if (typeof window === 'undefined') {
        return page.props.amountMaskDefault === true;
    }

    try {
        const stored = window.localStorage.getItem(storageKey());

        return stored === null
            ? page.props.amountMaskDefault === true
            : stored === '1';
    } catch {
        // Storage can be unavailable (private mode, disabled) — the account
        // default is still a real answer, so use it rather than "unmasked".
        return page.props.amountMaskDefault === true;
    }
}

const masked = ref(readInitial());

watch(masked, (value) => {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(storageKey(), value ? '1' : '0');
    } catch {
        // Nothing to fall back to — the toggle still works for this tab.
    }
});

watch(
    () => page.props.auth.user?.id,
    (userId, previousUserId) => {
        if (userId !== previousUserId) {
            masked.value = readInitial();
        }
    },
);

export type UseAmountMaskReturn = {
    masked: typeof masked;
    toggle: () => void;
    setMasked: (value: boolean) => void;
};

export function useAmountMask(): UseAmountMaskReturn {
    function toggle() {
        masked.value = !masked.value;
    }

    /**
     * Applies a value directly rather than flipping it — for the Settings
     * page, right after it saves a new account-wide default, so this device
     * reflects the change immediately instead of waiting for its next load.
     */
    function setMasked(value: boolean) {
        masked.value = value;
    }

    return { masked, toggle, setMasked };
}
