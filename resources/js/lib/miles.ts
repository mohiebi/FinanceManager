import type { MilesShortfall } from '@/types/miles';

type HttpErrorResponse = {
    status?: unknown;
    data?: unknown;
};

export function milesShortfallFromError(error: unknown): MilesShortfall | null {
    if (typeof error !== 'object' || error === null || !('response' in error)) {
        return null;
    }

    const response = error.response as HttpErrorResponse;
    const payload = response.data;

    if (
        response.status !== 402 ||
        typeof payload !== 'object' ||
        payload === null ||
        !('error' in payload) ||
        payload.error !== 'insufficient_miles'
    ) {
        return null;
    }

    const shortfall = payload as Partial<MilesShortfall>;

    if (
        typeof shortfall.available !== 'number' ||
        typeof shortfall.cost !== 'number' ||
        typeof shortfall.shortfall !== 'number' ||
        typeof shortfall.action !== 'string'
    ) {
        return null;
    }

    return shortfall as MilesShortfall;
}

export function dispatchMilesShortfall(error: unknown): boolean {
    const shortfall = milesShortfallFromError(error);

    if (shortfall === null || typeof window === 'undefined') {
        return false;
    }

    window.dispatchEvent(
        new CustomEvent<MilesShortfall>('miles:shortfall', {
            detail: shortfall,
        }),
    );

    return true;
}
