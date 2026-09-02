import { computed } from 'vue';
import type { ComputedRef, WritableComputedRef } from 'vue';

/**
 * A money field that reads as money while it is being typed.
 *
 * Two values, never one: `stored` is the plain digit string the form submits,
 * `display` is the same number with thousands separators. Binding the input to
 * `display` is what puts the commas in as the user types, while the request
 * still carries `200000000` rather than `200,000,000`.
 *
 * Persian and Arabic digits are folded to ASCII on the way in, along with their
 * decimal and thousands marks, so a Persian keyboard produces a number the
 * server can parse. Toman is a whole-unit currency here — a decimal point ends
 * the number rather than opening a fractional part — which is also why it is
 * the only currency offered the ×1000 button.
 */
export type UseMoneyInputOptions = {
    /** The raw value the form holds, as it is stored and submitted. */
    get: () => string;
    set: (value: string) => void;
    /** The currency that value is denominated in. */
    currency: () => string;
};

export type UseMoneyInputReturn = {
    /** Bind the input to this: grouped for reading, normalized on write. */
    display: WritableComputedRef<string>;
    /** Whether to show the money field with its ×1000 button. */
    isToman: ComputedRef<boolean>;
    /** Multiply by a thousand — the `000` button behind toman amounts. */
    multiplyByThousand: () => void;
    /** Re-normalize the stored value after the currency changes under it. */
    renormalize: () => void;
};

const PERSIAN_ZERO = 0x06f0;
const ARABIC_ZERO = 0x0660;

/**
 * Strip everything that is not part of the number, folding non-ASCII digits.
 */
export function normalizeMoneyInput(value: string, currency: string): string {
    const normalizedDigits = value
        .replace(/[۰-۹]/g, (digit) =>
            String(digit.charCodeAt(0) - PERSIAN_ZERO),
        )
        .replace(/[٠-٩]/g, (digit) => String(digit.charCodeAt(0) - ARABIC_ZERO))
        .replace(/٫/g, '.')
        .replace(/[٬،]/g, '');
    let normalized = '';
    let hasDecimal = false;

    for (const character of normalizedDigits) {
        if (/\d/.test(character)) {
            normalized += character;

            continue;
        }

        if (currency === 'toman' && character === '.') {
            break;
        }

        if (currency !== 'toman' && character === '.' && !hasDecimal) {
            normalized += character;
            hasDecimal = true;
        }
    }

    if (normalized.startsWith('.')) {
        return `0${normalized}`;
    }

    return normalized;
}

/**
 * Group the integer part in threes, leaving any decimals alone.
 */
export function formatMoneyInput(value: string, currency: string): string {
    if (value === '') {
        return '';
    }

    const normalized = normalizeMoneyInput(value, currency);
    const [integerPart, decimalPart] = normalized.split('.');
    const formattedInteger = (integerPart ?? '').replace(
        /\B(?=(\d{3})+(?!\d))/g,
        ',',
    );

    if (normalized.includes('.')) {
        return `${formattedInteger}.${decimalPart ?? ''}`;
    }

    return formattedInteger;
}

export function useMoneyInput(
    options: UseMoneyInputOptions,
): UseMoneyInputReturn {
    const isToman = computed(() => options.currency() === 'toman');

    const display = computed({
        get: () => formatMoneyInput(options.get(), options.currency()),
        set: (value: string) =>
            options.set(normalizeMoneyInput(value, options.currency())),
    });

    function multiplyByThousand(): void {
        const amount = Number(normalizeMoneyInput(options.get(), 'toman'));

        if (!Number.isFinite(amount) || amount <= 0) {
            return;
        }

        options.set(String(Math.trunc(amount * 1000)));
    }

    function renormalize(): void {
        options.set(normalizeMoneyInput(options.get(), options.currency()));
    }

    return { display, isToman, multiplyByThousand, renormalize };
}
