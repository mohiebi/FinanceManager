import assert from 'node:assert/strict';
import { afterEach, beforeEach, test } from 'node:test';

type Sent = { url: string; body: Record<string, unknown> };

const sent: Sent[] = [];
let originalConsoleError: typeof console.error;
let originalConsoleWarn: typeof console.warn;

function stubBrowser(): void {
    sent.length = 0;

    (globalThis as Record<string, unknown>).window = globalThis;
    (globalThis as Record<string, unknown>).Node = { COMMENT_NODE: 8 };
    (globalThis as Record<string, unknown>).document = {
        cookie: 'XSRF-TOKEN=tok%3Den',
        referrer: 'https://accounts.google.com/',
        getElementById: () => ({
            childElementCount: 0,
            // The shape production reported: an injected <script> sitting where
            // Vue expects its fragment-anchor comment.
            firstChild: { nodeType: 1, nodeName: 'SCRIPT' },
            querySelectorAll: () => [
                {
                    getAttribute: (name: string) =>
                        name === 'src' ? '/cdn-cgi/scripts/x.js' : null,
                    textContent: '',
                },
            ],
        }),
    };
    (globalThis as Record<string, unknown>).location = {
        pathname: '/dashboard',
        search: '',
    };
    (globalThis as Record<string, unknown>).addEventListener = () => {};
    (globalThis as Record<string, unknown>).fetch = (
        url: string,
        init: { body: string },
    ) => {
        sent.push({ url, body: JSON.parse(init.body) });

        return Promise.resolve({ ok: true });
    };
}

beforeEach(() => {
    originalConsoleError = console.error;
    originalConsoleWarn = console.warn;
    stubBrowser();
});

afterEach(() => {
    console.error = originalConsoleError;
    console.warn = originalConsoleWarn;
});

test('a boot report records that the server sent no SSR markup', async () => {
    const { captureSsrPresence } =
        await import('../../resources/js/lib/diagnostics.ts?boot');

    captureSsrPresence('Dashboard');

    assert.equal(sent.length, 1);
    assert.equal(sent[0].url, '/_diagnostics/client');
    assert.equal(sent[0].body.kind, 'boot');
    assert.equal(sent[0].body.ssrMarkup, false);
    assert.equal(sent[0].body.ssrChildren, 0);
    assert.equal(sent[0].body.component, 'Dashboard');
    // The OAuth entry is identifiable by its referrer.
    assert.equal(sent[0].body.referrer, 'https://accounts.google.com/');
    // The whole point: name what is sitting where hydration begins.
    assert.match(String(sent[0].body.message), /first_child=script/);
    assert.match(String(sent[0].body.message), /scripts_in_app=1/);
    assert.match(String(sent[0].body.message), /cdn-cgi/);
});

test('hydration messages are forwarded and other console output is not', async () => {
    const { installDiagnostics } =
        await import('../../resources/js/lib/diagnostics.ts?console');

    const passedThrough: string[] = [];
    console.error = (...args: unknown[]) => {
        passedThrough.push(String(args[0]));
    };

    installDiagnostics();

    console.error('Hydration node mismatch in <Dashboard>');
    console.error('some unrelated error');

    assert.equal(sent.length, 1);
    assert.equal(sent[0].body.kind, 'hydration');
    assert.match(String(sent[0].body.message), /Hydration node mismatch/);

    // The original console still receives everything — instrumentation must not
    // swallow output a developer is relying on.
    assert.deepEqual(passedThrough, [
        'Hydration node mismatch in <Dashboard>',
        'some unrelated error',
    ]);
});

test('an identical message is reported once, not once per element', async () => {
    const { installDiagnostics } =
        await import('../../resources/js/lib/diagnostics.ts?dedupe');

    console.error = () => {};
    installDiagnostics();

    for (let i = 0; i < 50; i++) {
        console.error('Hydration text mismatch');
    }

    assert.equal(sent.length, 1);
});
