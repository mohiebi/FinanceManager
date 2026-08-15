export function advisorGenerationErrorKey(error: unknown): string {
    if (typeof error === 'object' && error !== null) {
        const response = 'response' in error ? error.response : null;
        const status =
            typeof response === 'object' &&
            response !== null &&
            'status' in response &&
            typeof response.status === 'number'
                ? response.status
                : null;

        if (status === 429) {
            return 'advisor.validation.recommendation_rate_limited';
        }

        if (status !== null && status >= 500) {
            return 'advisor.validation.provider_failure';
        }

        if ('name' in error && error.name === 'HttpNetworkError') {
            return 'advisor.validation.provider_failure';
        }
    }

    return 'advisor.recommendation.failed';
}

const failureKeys: Record<string, string> = {
    provider_failure: 'advisor.validation.provider_failure',
    pending_payload_expired: 'advisor.validation.pending_payload_expired',
};

export function advisorRecommendationFailureKey(
    failureCode: string | undefined,
): string {
    return (
        (failureCode !== undefined ? failureKeys[failureCode] : undefined) ??
        'advisor.recommendation.failed'
    );
}
