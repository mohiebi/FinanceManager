import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const source = readFileSync(
    new URL('../../resources/js/pages/Advisor/Profile.vue', import.meta.url),
    'utf8',
);

test('the dossier reads top to bottom: profile, guardrails, assets, hand-off', () => {
    // The order is the argument: what CashPilot derived, what it will not let
    // the AI exceed, what the AI may use, and only then the button that hands
    // the whole thing over.
    const dossier = source.indexOf('rounded-[16px] border border-[#d9c48f]/22');
    const guardrails = source.indexOf("t('advisor.profile.constraints')");
    const assets = source.indexOf("t('advisor.profile.assets')");
    const handOff = source.indexOf('border-[#02cd86]/22');

    assert.ok(dossier >= 0);
    assert.ok(guardrails > dossier);
    assert.ok(assets > guardrails);
    assert.ok(handOff > assets);
});

test('the deterministic stamp stays on the document', () => {
    // Load-bearing copy: the separation between deterministic scoring and AI
    // generation is what the Pro tier is selling, so it is printed beside the
    // scores rather than explained somewhere else.
    assert.match(source, /t\('advisor\.profile\.deterministic'\)/);
    assert.match(source, /t\('advisor\.profile\.derived_scores'\)/);
});

test('the seal band prints a real reference, date and scoring version', () => {
    // A dossier that says "No. 0000" for everyone is set dressing. These come
    // from the profile row.
    assert.match(source, /documentNumber\(props\.profile\.id\)/);
    assert.match(source, /sealDate\(props\.profile\.completed_at, calendar\)/);
    assert.match(source, /props\.profile\.scoring_version/);
});

test('gold marks a score only once it is high enough to be shaping the plan', () => {
    assert.match(source, /value >= 70 \? '#d9c48f' : '#02cd86'/);
});

test('every figure on the dossier is mono and tabular', () => {
    // .advisor-mono carries font-variant-numeric: tabular-nums, so a column of
    // scores cannot shift as it renders.
    for (const figure of [
        'riskScore',
        'maximum_tolerated_drawdown',
        'knowledge_score',
    ]) {
        const index = source.indexOf(figure);
        assert.ok(index >= 0, `${figure} should be rendered`);
    }

    assert.ok(
        source.split('advisor-mono').length - 1 > 12,
        'the dossier is built out of mono micro-labels and figures',
    );
});
