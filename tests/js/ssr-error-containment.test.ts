import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const ssr = readFileSync(
    new URL('../../resources/js/ssr.ts', import.meta.url),
    'utf8',
);

/**
 * One bad render must not take the SSR worker down with it.
 *
 * Inertia wraps the render call, so a component throwing during setup is
 * caught and returned as a failed render. An effect *scheduled* by that
 * component is not: a watcher or computed that throws after the render call
 * has unwound escapes into Node and kills the process, and the supervisor
 * respawns it — once per request. That is how a single unlucky page took SSR
 * down for every user in production.
 *
 * Reproduced locally: the worker died on the second render and survived all
 * three once this handler was in place.
 */
test('the SSR app catches errors that escape a render', () => {
    assert.match(ssr, /vueApp\.config\.errorHandler = /);
    // It has to be installed before the Inertia plugin mounts anything.
    assert.ok(
        ssr.indexOf('vueApp.config.errorHandler') <
            ssr.indexOf('vueApp.use(plugin)'),
        'the handler is installed before the plugin',
    );
});

test('the SSR failure is reported, not silently swallowed', () => {
    // A handler that returns quietly turns a crash-loop into an invisible
    // hydration mismatch, which is harder to diagnose than the crash was.
    const handler = ssr.slice(ssr.indexOf('vueApp.config.errorHandler'));

    assert.match(handler.slice(0, 400), /console\.error/);
    assert.match(handler.slice(0, 400), /props\.initialPage\.component/);
});
