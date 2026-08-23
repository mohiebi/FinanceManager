import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

import {
    buildBreakdown,
    buildExposureBreakdown,
    buildSnapshot,
    formatToman,
    type PortfolioAsset,
    type PortfolioExposure,
    signedQuantityFor,
} from '../../resources/js/lib/portfolio.ts';

/**
 * The same fixture is asserted by tests/Feature/BuildPortfolioBreakdownTest.php.
 *
 * With the vault armed the browser computes this breakdown instead of the server,
 * so two implementations exist. If either drifts, both suites fail and name the
 * exact asset.
 */
const vectors = JSON.parse(
    readFileSync(
        new URL('../fixtures/portfolio-vectors.json', import.meta.url),
        'utf8',
    ),
);

function breakdownFor(target: string) {
    return buildBreakdown(
        vectors.entries,
        vectors.assets,
        target as 'toman' | 'usd' | 'eur',
        vectors.rates,
    );
}

test('every breakdown matches the server', () => {
    assert.ok(vectors.cases.length > 0);

    for (const testCase of vectors.cases) {
        const { assets, summary } = breakdownFor(testCase.target);

        // Order is part of the contract — the server sorts by current value desc.
        assert.deepEqual(
            assets.map((asset) => asset.key),
            testCase.assets.map((asset: { key: string }) => asset.key),
            `${testCase.target}: asset order`,
        );

        for (const [index, expected] of testCase.assets.entries()) {
            for (const [field, value] of Object.entries(expected)) {
                assert.deepEqual(
                    assets[index]![field as keyof (typeof assets)[number]],
                    value,
                    `${testCase.target}: ${expected.key}.${field}`,
                );
            }
        }

        for (const [field, value] of Object.entries(testCase.summary)) {
            assert.deepEqual(
                summary[field as keyof typeof summary],
                value,
                `${testCase.target}: summary.${field}`,
            );
        }
    }
});

test('a partial sale leaves the remaining cost basis untouched', () => {
    const { entries, target, asset, summary } = vectors.disposal;

    const breakdown = buildBreakdown(
        entries,
        vectors.assets,
        target,
        vectors.rates,
    );

    assert.equal(breakdown.assets.length, 1);

    for (const [field, value] of Object.entries(asset)) {
        assert.deepEqual(
            breakdown.assets[0]![field as keyof PortfolioAsset],
            value,
            `disposal: ${field}`,
        );
    }

    for (const [field, value] of Object.entries(summary)) {
        assert.deepEqual(
            breakdown.summary[field as keyof typeof breakdown.summary],
            value,
            `disposal: summary.${field}`,
        );
    }
});

test('realised and unrealised profit are never mixed together', () => {
    const { entries, target } = vectors.disposal;
    const { summary } = buildBreakdown(
        entries,
        vectors.assets,
        target,
        vectors.rates,
    );

    // Banked money and a paper figure that moves with the market. Adding them
    // would produce a number that means nothing.
    assert.equal(summary.total_realised_pnl, 4_000_000);
    assert.equal(summary.total_pnl, 3_000_000);
});

test('selling everything closes the position without inventing a profit', () => {
    const { summary, assets } = buildBreakdown(
        [
            {
                investment_asset_id: 1,
                kind: 'buy',
                quantity: 2,
                cost_basis: 4_000_000,
                cost_basis_currency: 'toman',
                sale_price: null,
                sale_price_currency: null,
            },
            {
                investment_asset_id: 1,
                kind: 'sell',
                quantity: -2,
                cost_basis: 4_000_000,
                cost_basis_currency: 'toman',
                sale_price: 5_000_000,
                sale_price_currency: 'toman',
            },
        ],
        vectors.assets,
        'toman',
        vectors.rates,
    );

    // Nothing held, so there is no unrealised figure to report — but the gain
    // that was actually banked has to survive.
    assert.equal(assets[0]!.quantity, 0);
    assert.equal(assets[0]!.pnl, null);
    assert.equal(summary.total_pnl, null);
    assert.equal(summary.total_realised_pnl, 2_000_000);
    assert.equal(summary.has_realised_data, true);
});

test('an unpriced holding still reports its quantity', () => {
    const { assets, summary } = buildBreakdown(
        [
            {
                investment_asset_id: 9,
                kind: 'buy' as const,
                quantity: 4,
                cost_basis: null,
                cost_basis_currency: null,
                sale_price: null,
                sale_price_currency: null,
            },
        ],
        [
            {
                id: 9,
                key: 'unpriced',
                label: 'Unpriced',
                icon: null,
                icon_svg: null,
                color: '#fff',
                unit: 'unit',
                price: 0,
                price_available: false,
            },
        ],
        'toman',
        vectors.rates,
    );

    assert.equal(assets[0]!.quantity, 4);
    assert.equal(assets[0]!.pnl, null);
    assert.equal(summary.has_cost_basis_data, false);
    assert.equal(summary.total_pnl, null);
});

test('an entry whose asset is missing is skipped rather than counted as zero', () => {
    const { assets, summary } = buildBreakdown(
        [
            {
                investment_asset_id: 404,
                kind: 'buy' as const,
                quantity: 7,
                cost_basis: null,
                cost_basis_currency: null,
                sale_price: null,
                sale_price_currency: null,
            },
        ],
        [],
        'toman',
        vectors.rates,
    );

    assert.equal(assets.length, 0);
    assert.equal(summary.asset_count, 0);
});

test('the dashboard snapshot takes the top three holdings by share', () => {
    const snapshot = buildSnapshot(breakdownFor('toman'));

    assert.ok(snapshot !== null);
    assert.equal(snapshot.net_worth_formatted, '24,000,000');
    assert.equal(snapshot.asset_count, 2);
    assert.deepEqual(
        snapshot.top_assets.map((asset) => [asset.key, asset.share]),
        [
            ['parity-gold', 63],
            ['parity-dollar', 38],
        ],
    );
});

test('no holdings means no snapshot at all', () => {
    assert.equal(
        buildSnapshot(buildBreakdown([], [], 'toman', vectors.rates)),
        null,
    );
});

/**
 * The sign rule itself, pinned on its own.
 *
 * Every holding total in this file is a plain sum, so a disposal only subtracts
 * because its stored quantity is negative. That invariant has been broken twice
 * in two places — once on the server, where an edit rewrote a sale's sign, and
 * once here, where the sell dialog sealed the magnitude the user typed and left
 * the browser to add a sale to the holding it should have taken away from. With
 * the vault armed there is no server-side second chance: a sealed quantity is
 * the only copy, and nothing downstream can sign it after the fact.
 */
test('a disposal is signed negative and a purchase positive, whatever is typed', () => {
    assert.equal(signedQuantityFor('sell', 2), -2);
    assert.equal(signedQuantityFor('buy', 2), 2);

    // Idempotent from either direction, so a value that already carries its sign
    // survives a second pass unchanged.
    assert.equal(signedQuantityFor('sell', -2), -2);
    assert.equal(signedQuantityFor('buy', -2), 2);

    assert.equal(signedQuantityFor('sell', 0), 0);
    assert.equal(signedQuantityFor('sell', 0.00000001), -0.00000001);
});

test('a sale signed the browser way nets the same holding as the server way', () => {
    // The armed-vault path and the plaintext path have to agree, because the same
    // breakdown code reads both.
    const { assets } = buildBreakdown(
        [
            {
                investment_asset_id: 1,
                kind: 'buy',
                quantity: signedQuantityFor('buy', 5),
                cost_basis: 4_000_000,
                cost_basis_currency: 'toman',
                sale_price: null,
                sale_price_currency: null,
            },
            {
                investment_asset_id: 1,
                kind: 'sell',
                // What the dialog now seals: the magnitude typed, signed first.
                quantity: signedQuantityFor('sell', 2),
                cost_basis: 4_000_000,
                cost_basis_currency: 'toman',
                sale_price: 6_000_000,
                sale_price_currency: 'toman',
            },
        ],
        vectors.assets,
        'toman',
        vectors.rates,
    );

    const gold = assets.find(
        (asset: PortfolioAsset) => asset.id === 1,
    ) as PortfolioAsset;

    assert.equal(gold.quantity, 3);
});

/**
 * Two holdings that are one bet.
 *
 * Half coins track parity gold, so the pair has to roll up into a single gold
 * exposure — with the vault armed this is the only side that can work that out.
 * The same fixture is asserted by BuildPortfolioBreakdownTest.php.
 */
test('the exposure roll-up matches the server', () => {
    const fixture = vectors.exposure;
    const { assets } = buildBreakdown(
        fixture.entries,
        fixture.assets,
        fixture.target,
        vectors.rates,
    );

    const result = buildExposureBreakdown(assets, (amount) =>
        formatToman(amount, fixture.target, vectors.rates),
    );

    // Order is part of the contract — largest exposure first.
    assert.deepEqual(
        result.exposures.map((group) => group.label),
        fixture.exposures.map((group: { label: string }) => group.label),
        'exposure order',
    );
    assert.deepEqual(
        result.classes.map((group) => group.key),
        fixture.classes.map((group: { key: string }) => group.key),
        'class order',
    );
    assert.equal(result.has_unpriced_assets, fixture.has_unpriced_assets);

    for (const [index, expected] of fixture.exposures.entries()) {
        const group = result.exposures[index]!;

        assert.deepEqual(
            group.members.map((member) => member.slug),
            expected.member_slugs,
            `exposure ${expected.label}: members`,
        );

        for (const [field, value] of Object.entries(expected)) {
            if (field === 'member_slugs') {
                continue;
            }

            assert.deepEqual(
                group[field as keyof PortfolioExposure],
                value,
                `exposure ${expected.label}.${field}`,
            );
        }
    }

    for (const [index, expected] of fixture.classes.entries()) {
        for (const [field, value] of Object.entries(expected)) {
            assert.deepEqual(
                result.classes[index]![
                    field as keyof (typeof result.classes)[number]
                ],
                value,
                `class ${expected.key}.${field}`,
            );
        }
    }
});

/**
 * A group whose members cannot all be converted reports no total at all.
 *
 * Returning a partial sum would look complete and be wrong — the one number a
 * metals holder would act on directly.
 */
test('an exposure with an unstated conversion reports no equivalent quantity', () => {
    const fixture = vectors.exposure;
    const assetsWithoutRatio = fixture.assets.map(
        (asset: { underlying_ratio: number | null }) => ({
            ...asset,
            underlying_ratio: null,
        }),
    );

    const { assets } = buildBreakdown(
        fixture.entries,
        assetsWithoutRatio,
        fixture.target,
        vectors.rates,
    );

    const { exposures } = buildExposureBreakdown(assets, (amount) =>
        formatToman(amount, fixture.target, vectors.rates),
    );

    assert.equal(exposures[0]!.equivalent_quantity, null);
    // The value roll-up is unaffected: it never needed the conversion.
    assert.equal(exposures[0]!.value, 34_400_000);
});
