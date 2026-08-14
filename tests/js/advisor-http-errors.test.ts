import assert from 'node:assert/strict';
import { test } from 'node:test';
import { advisorGenerationErrorKey } from '../../resources/js/lib/advisor/http-errors.ts';

test('a recommendation rate limit never exposes the raw HTTP error', () => {
    const error = { response: { status: 429 } };

    assert.equal(
        advisorGenerationErrorKey(error),
        'advisor.validation.recommendation_rate_limited',
    );
});

test('provider and network failures use the friendly availability message', () => {
    const providerError = { response: { status: 502 } };

    assert.equal(
        advisorGenerationErrorKey(providerError),
        'advisor.validation.provider_failure',
    );
    assert.equal(
        advisorGenerationErrorKey({ name: 'HttpNetworkError' }),
        'advisor.validation.provider_failure',
    );
});
