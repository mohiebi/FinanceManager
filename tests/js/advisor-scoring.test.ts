import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { scoreInvestorProfile } from '../../resources/js/lib/advisor/profile.ts';

type Vector = {
    name: string;
    input: Record<string, unknown>;
    expected: {
        scores: Record<string, number>;
        risk_band: string;
        persona: string;
        maximum_tolerated_drawdown: number;
        warnings: string[];
    };
};

const vectors = JSON.parse(await readFile(new URL('../fixtures/advisor-score-vectors.json', import.meta.url), 'utf8')) as { cases: Vector[] };

for (const vector of vectors.cases) {
    test(`browser scorer matches canonical vector: ${vector.name}`, () => {
        const actual = scoreInvestorProfile(vector.input);
        assert.deepEqual(actual.scores, vector.expected.scores);
        assert.equal(actual.risk_band, vector.expected.risk_band);
        assert.equal(actual.persona, vector.expected.persona);
        assert.equal(actual.maximum_tolerated_drawdown, vector.expected.maximum_tolerated_drawdown);
        assert.deepEqual(actual.warnings, vector.expected.warnings);
    });
}
