import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const source = readFileSync(
    new URL('../../resources/js/pages/Advisor/Profile.vue', import.meta.url),
    'utf8',
);

test('the AI portfolio action follows the investor profile before guardrails', () => {
    const profileCard = source.indexOf(
        'mx-auto max-w-6xl overflow-hidden rounded-[16px]',
    );
    const aiAction = source.indexOf(
        'max-w-6xl rounded-[16px] border border-[#02CD86]/20',
    );
    const guardrails = source.indexOf(
        'grid max-w-6xl gap-[18px] lg:grid-cols-2',
    );

    assert.ok(profileCard >= 0);
    assert.ok(aiAction > profileCard);
    assert.ok(guardrails > aiAction);
});
