/**
 * Currency conversion in the browser.
 *
 * A deliberate port of App\Actions\Transactions\CurrencyConverter: once amounts
 * are encrypted with the vault armed, the server cannot sum them, so totals have
 * to be computed here from the decrypted values.
 *
 * The two implementations are kept honest by a shared fixture — see
 * tests/fixtures/money-vectors.json, asserted by both CurrencyConverterTest and
 * tests/js/money.test.ts. Two copies of money maths is a drift risk; a checked
 * invariant is not.
 *
 * The rates are public market prices, not user secrets, so shipping them as props
 * costs nothing in privacy.
 */
export type CurrencyCode = 'toman' | 'usd' | 'eur';

export type Rates = {
    /** Toman per 1 USD. 0 when live prices are unavailable. */
    tomanPerUsd: number;
    /** Toman per 1 EUR. 0 when live prices are unavailable. */
    tomanPerEur: number;
};

/**
 * Round half away from zero, matching PHP's round().
 *
 * Two differences from a naive Math.round(value * 100) / 100, both of which
 * produce cent-level disagreements with the server:
 *
 * 1. Math.round breaks ties toward +Infinity, so -1.005 would round the wrong way.
 * 2. More subtly, 1.005 * 100 is 100.49999999999999 in IEEE754, so a naive round
 *    gives 1.00 — while PHP's round() applies a precision correction first and
 *    gives 1.01. toPrecision(15) reproduces that correction.
 */
function round2(value: number): number {
    const scaled = Number((Math.abs(value) * 100).toPrecision(15));
    const rounded = Math.round(scaled) / 100;

    return value < 0 ? -rounded : rounded;
}

function usdPerEur(rates: Rates): number {
    return rates.tomanPerUsd > 0 ? rates.tomanPerEur / rates.tomanPerUsd : 0;
}

function toUsd(amount: number, from: CurrencyCode, rates: Rates): number {
    if (from === 'usd') {
        return amount;
    }

    if (from === 'toman') {
        return rates.tomanPerUsd > 0 ? amount / rates.tomanPerUsd : 0;
    }

    return amount * usdPerEur(rates);
}

function fromUsd(amountInUsd: number, to: CurrencyCode, rates: Rates): number {
    if (to === 'usd') {
        return amountInUsd;
    }

    if (to === 'toman') {
        return amountInUsd * rates.tomanPerUsd;
    }

    const rate = usdPerEur(rates);

    return rate > 0 ? amountInUsd / rate : 0;
}

export function convert(
    amount: number | string,
    from: CurrencyCode,
    to: CurrencyCode,
    rates: Rates,
): number {
    const value = Number(amount) || 0;

    if (from === to) {
        return round2(value);
    }

    return round2(fromUsd(toUsd(value, from, rates), to, rates));
}

export function format(
    amount: number | string,
    from: CurrencyCode,
    to: CurrencyCode,
    rates: Rates,
): string {
    return convert(amount, from, to, rates).toFixed(2);
}

export function sumFormatted(
    items: ReadonlyArray<{ amount: number | string; currency: CurrencyCode }>,
    target: CurrencyCode,
    rates: Rates,
): string {
    const total = items.reduce(
        (carry, item) =>
            carry + convert(item.amount, item.currency, target, rates),
        0,
    );

    return round2(total).toFixed(2);
}
