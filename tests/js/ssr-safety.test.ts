import assert from 'node:assert/strict';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { test } from 'node:test';

const root = fileURLToPath(new URL('../../resources/js', import.meta.url));

function walk(dir: string, extension: string): string[] {
    return readdirSync(dir).flatMap((entry) => {
        const path = join(dir, entry);

        if (statSync(path).isDirectory()) {
            return walk(path, extension);
        }

        return path.endsWith(extension) ? [path] : [];
    });
}

/**
 * The bug that has now bitten this app twice.
 *
 * `usePage()` resolves the page Inertia has currently installed. At module
 * scope it runs when the module is first imported — during SSR that is before
 * Inertia installs the page for the request being rendered, and the value it
 * captures is then shared by every concurrent render in a long-lived Node
 * worker. The first time it was `useAmountMask` reading `amountMaskDefault`
 * off an undefined page and crash-looping the SSR worker in production; the
 * second was `useCurrentUrl` building a module-level computed over `page.url`.
 *
 * A `.vue` file's `<script setup>` body *is* component setup, so only plain
 * modules are checked. Inside them, a call at zero indentation is at module
 * scope; one indented is inside a function, which is where it belongs.
 */
test('composables never call usePage() at module scope', () => {
    const offenders: string[] = [];

    for (const path of walk(root, '.ts')) {
        const source = readFileSync(path, 'utf8');

        if (!source.includes('usePage')) {
            continue;
        }

        source.split('\n').forEach((line, index) => {
            if (/^(?:const|let|var)\s+\w+\s*=\s*usePage\(\)/.test(line)) {
                offenders.push(
                    `${path.slice(root.length + 1)}:${index + 1} — ${line.trim()}`,
                );
            }
        });
    }

    assert.deepEqual(
        offenders,
        [],
        `usePage() must be called inside the composable, not at module scope:\n${offenders.join('\n')}`,
    );
});

test('useCurrentUrl builds its page reference per call', () => {
    const source = readFileSync(
        join(root, 'composables/useCurrentUrl.ts'),
        'utf8',
    );
    const body = source.slice(source.indexOf('export function useCurrentUrl'));

    // Both the page reference and the computed derived from it have to live
    // inside the function — a module-level computed leaks across SSR requests
    // even when the page reference itself is fine.
    assert.match(body, /const page = usePage\(\)/);
    assert.match(body, /const currentUrlReactive = computed\(/);
});

/**
 * SSR has no `window`. Anything reading it during setup or render has to guard,
 * and the guard has to come first — `window.x ?? fallback` still throws.
 */
test('the amount mask keeps its SSR state request-local', () => {
    const source = readFileSync(
        join(root, 'composables/useAmountMask.ts'),
        'utf8',
    );
    const body = source.slice(source.indexOf('export function useAmountMask'));

    assert.match(body, /const page = usePage\(\)/);
    assert.match(body, /typeof window === 'undefined'/);
    // A module-level ref would carry one user's choice into the next request.
    assert.match(body, /Keep SSR state request-local/);
});

/**
 * jalaali-js ships CommonJS.
 *
 * A named import from it resolves through a bundler but not through Node's ESM
 * loader, so it only breaks in a built SSR bundle — which means it only breaks
 * in production. Worse, it fails at *module instantiation*, before any
 * component renders, so Vue's errorHandler cannot contain it and the whole
 * page falls back to client rendering.
 *
 * BirthdatePicker had one, which took SSR down for every page that reaches it —
 * including the login screen, the first page anyone loads. Destructuring the
 * default export works under both loaders.
 */
test('CommonJS packages are imported by default, never by name', () => {
    const offenders: string[] = [];

    for (const extension of ['.ts', '.vue']) {
        for (const path of walk(root, extension)) {
            const source = readFileSync(path, 'utf8');

            if (!source.includes('jalaali-js')) {
                continue;
            }

            if (/import\s*\{[^}]*\}\s*from\s*['"]jalaali-js['"]/.test(source)) {
                offenders.push(path.slice(root.length + 1));
            }
        }
    }

    assert.deepEqual(
        offenders,
        [],
        `import jalaali from 'jalaali-js' and destructure instead:\n${offenders.join('\n')}`,
    );
});

/**
 * The first client render has to paint what the server painted.
 *
 * The server only knows the account default; it cannot read a device's
 * localStorage. A client that consults storage during setup therefore
 * disagrees with the server whenever the two differ — on the class and the
 * text of every amount on the page. The dashboard is mostly amounts, and a
 * mismatch that large is how hydration adopts a tree Vue cannot then patch.
 */
test('the amount mask does not read storage before hydration finishes', () => {
    const source = readFileSync(
        join(root, 'composables/useAmountMask.ts'),
        'utf8',
    );
    const body = source.slice(source.indexOf('export function useAmountMask'));

    // Storage is adopted on mount, not called straight through in setup.
    assert.match(body, /onMounted\(syncBrowserUser\)/);
    assert.doesNotMatch(body, /^\s{4}syncBrowserUser\(\);$/m);
    // And the pre-mount value is the same one the server used.
    assert.match(body, /browserMasked\.value = amountMaskDefault\(page\)/);
});

/**
 * A watcher callback runs synchronously during setup, so it runs on the server
 * too. Landing built its JSON-LD tag in one and threw `document is not defined`
 * on every server render, which Inertia swallowed into a silent client-side
 * fallback — the page was simply never server-rendered.
 */
test('browser APIs are reached from a lifecycle hook, not a watcher', () => {
    const landing = readFileSync(join(root, 'pages/Landing.vue'), 'utf8');
    const effect = landing.indexOf('watchEffect(() => {');
    const mounted = landing.indexOf('onMounted(() => {');

    assert.ok(
        mounted >= 0 && mounted < effect,
        'the effect is inside onMounted',
    );
    assert.match(landing, /document\.createElement\('script'\)/);
});
