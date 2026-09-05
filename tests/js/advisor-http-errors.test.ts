import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    advisorGenerationErrorKey,
    advisorRecommendationFailureKey,
} from '../../resources/js/lib/advisor/http-errors.ts';
import { milesShortfallFromError } from '../../resources/js/lib/miles.ts';

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

test('stored failure codes never surface as raw technical messages', () => {
    assert.equal(
        advisorRecommendationFailureKey('provider_failure'),
        'advisor.validation.provider_failure',
    );
    assert.equal(
        advisorRecommendationFailureKey('validation_failed'),
        'advisor.recommendation.failed',
    );
});

test('a structured 402 is recognized without treating other failures as Miles errors', () => {
    const payload = {
        error: 'insufficient_miles',
        available: 40,
        cost: 80,
        shortfall: 40,
        action: 'advisor_guidance',
    };

    assert.deepEqual(
        milesShortfallFromError({ response: { status: 402, data: payload } }),
        payload,
    );
    assert.equal(
        milesShortfallFromError({ response: { status: 422, data: payload } }),
        null,
    );
});

test('a 402 body is recognized whether the client parsed it or left it a string', () => {
    const payload = {
        error: 'insufficient_miles',
        available: 53,
        cost: 75,
        shortfall: 22,
        action: 'cosmetic',
    };

    // Inertia's XHR client hands the body over already parsed; other paths
    // deliver the same JSON as text. Reading only one shape is what let the
    // generic "must receive a valid Inertia response" screen through.
    assert.deepEqual(
        milesShortfallFromError({ response: { status: 402, data: payload } }),
        payload,
    );
    assert.deepEqual(
        milesShortfallFromError({
            response: { status: 402, data: JSON.stringify(payload) },
        }),
        payload,
    );
    assert.equal(
        milesShortfallFromError({
            response: { status: 402, data: 'not json' },
        }),
        null,
    );
});
