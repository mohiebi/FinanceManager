import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const source = readFileSync(
    new URL(
        '../../resources/js/pages/Advisor/Recommendation.vue',
        import.meta.url,
    ),
    'utf8',
);

test('the Advisor consultation shows an accessible loading state', () => {
    assert.match(source, /const isConsulting = ref\(false\)/);
    assert.match(source, /v-if="isConsulting"\s+role="status"/);
    assert.match(source, /aria-live="polite"/);
    assert.match(source, /:aria-busy="isConsulting"/);
    assert.match(source, /motion-reduce:animate-none/);
});

test('the consultation form prevents duplicate submissions while loading', () => {
    assert.match(
        source,
        /if \(!text \|\| !payload\.value \|\| isConsulting\.value\)/,
    );
    assert.match(
        source,
        /:disabled="isConsulting \|\| !chatMessage\.trim\(\)"/,
    );
    assert.match(source, /finally \{\s+isConsulting\.value = false;/);
});
