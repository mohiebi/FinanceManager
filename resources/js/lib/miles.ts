import type { MilesShortfall } from '@/types/miles';

type HttpErrorResponse = {
    status?: unknown;
    data?: unknown;
};

/**
 * The body as an object, whichever way the client handed it over.
 *
 * Inertia's XHR client parses a JSON response for us, but the same payload
 * arrives as a raw string through other paths - so a reader that assumes
 * either one silently misses half the cases and lets the generic error screen
 * through instead of the shortfall dialog.
 */
function decodeBody(data: unknown): unknown {
    if (typeof data !== 'string') {
        return data;
    }

    try {
        return JSON.parse(data);
    } catch {
        return null;
    }
}

export function milesShortfallFromError(error: unknown): MilesShortfall | null {
    if (typeof error !== 'object' || error === null || !('response' in error)) {
        return null;
    }

    const response = error.response as HttpErrorResponse;
    const payload = decodeBody(response.data);

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
