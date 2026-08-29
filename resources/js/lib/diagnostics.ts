/**
 * Browser-side instrumentation for the post-OAuth SSR/hydration investigation.
 *
 * A hydration mismatch leaves no server-side trace: the server sent correct
 * HTML and logged a clean 200, and the damage happens afterwards in the
 * browser. Vue also strips its hydration warnings from production builds, so
 * even the user's console is silent unless
 * `__VUE_PROD_HYDRATION_MISMATCH_DETAILS__` is compiled in (see vite.config.ts).
 *
 * This module turns both into server log lines. Remove it, the route, and the
 * Vite flag once the cause is found.
 */

const ENDPOINT = '/_diagnostics/client';

/** One report per distinct message, so a mismatch inside a v-for cannot
 *  generate hundreds of identical requests. */
const alreadySent = new Set<string>();

let bootComponent: string | null = null;

type Report = {
    kind: 'boot' | 'hydration' | 'error' | 'rejection' | 'inertia';
    component?: string | null;
    message?: string;
    stack?: string;
    ssrMarkup?: boolean;
    ssrChildren?: number;
};

function xsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : null;
}

function send(report: Report): void {
    const fingerprint = `${report.kind}:${report.message ?? ''}`;

    if (alreadySent.has(fingerprint)) {
        return;
    }

    alreadySent.add(fingerprint);

    const token = xsrfToken();

    try {
        void fetch(ENDPOINT, {
            method: 'POST',
            // keepalive so a report fired during a failing hydration still
            // leaves the page even if navigation follows immediately.
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...(token ? { 'X-XSRF-TOKEN': token } : {}),
            },
            body: JSON.stringify({
                component: bootComponent,
                url: window.location.pathname + window.location.search,
                referrer: document.referrer || null,
                ...report,
            }),
        }).catch(() => {
            // Diagnostics must never become a second failure mode.
        });
    } catch {
        // Same.
    }
}

/**
 * Whether the server actually sent rendered markup for this document.
 *
 * Must be read before Inertia mounts, because mounting fills the element in
 * either case and erases the distinction. An empty root means SSR was skipped
 * or failed and the page is being client-rendered — invisible in an access
 * log, where it is the same URL and the same 200.
 */
export function captureSsrPresence(component: string | null): void {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    bootComponent = component;

    const root = document.getElementById('app');
    const children = root?.childElementCount ?? 0;

    send({
        kind: 'boot',
        ssrMarkup: children > 0,
        ssrChildren: children,
    });
}

/** Vue routes hydration mismatches through here once the prod flag is on. */
export function reportHydrationIssue(message: string): void {
    send({ kind: 'hydration', message });
}

export function reportError(kind: 'error' | 'rejection', error: unknown): void {
    send({
        kind,
        message: error instanceof Error ? error.message : String(error),
        stack: error instanceof Error ? (error.stack ?? undefined) : undefined,
    });
}

export function reportInertiaEvent(message: string): void {
    send({ kind: 'inertia', message });
}

/**
 * Vue's production hydration errors are written straight to the console rather
 * than through `warnHandler`, so the console is where they have to be caught.
 * Only hydration-related lines are forwarded; everything else passes through
 * untouched.
 */
export function installDiagnostics(): void {
    if (typeof window === 'undefined') {
        return;
    }

    for (const level of ['warn', 'error'] as const) {
        const original = console[level].bind(console);

        console[level] = (...args: unknown[]): void => {
            original(...args);

            const text = args
                .map((arg) =>
                    arg instanceof Error ? arg.message : String(arg),
                )
                .join(' ');

            if (/hydrat/i.test(text)) {
                reportHydrationIssue(text.slice(0, 3000));
            }
        };
    }

    window.addEventListener('error', (event) => {
        reportError('error', event.error ?? event.message);
    });

    window.addEventListener('unhandledrejection', (event) => {
        reportError('rejection', event.reason);
    });
}
