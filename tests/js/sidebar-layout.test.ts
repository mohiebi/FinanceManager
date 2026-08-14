import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const sidebar = readFileSync(
    new URL('../../resources/js/components/AppSidebar.vue', import.meta.url),
    'utf8',
);

function classesFor(attribute: string): string {
    const element = sidebar.match(
        new RegExp(`<nav\\s+${attribute}\\s+class="([^"]+)"`),
    );

    assert.ok(element, `Could not find nav with ${attribute}`);

    return element[1];
}

function divClassesFor(attribute: string): string {
    const element = sidebar.match(
        new RegExp(`<div\\s+${attribute}\\s+class="([^"]+)"`),
    );

    assert.ok(element, `Could not find div with ${attribute}`);

    return element[1];
}

test('the sidebar scrolls only its primary navigation region', () => {
    const scrollClasses = classesFor('data-sidebar-scroll');

    assert.match(scrollClasses, /\bmin-h-0\b/);
    assert.match(scrollClasses, /\bflex-1\b/);
    assert.match(scrollClasses, /\boverflow-y-auto\b/);
    assert.match(scrollClasses, /\boverscroll-contain\b/);
});

test('the account navigation remains outside the scrolling region', () => {
    const accountClasses = classesFor('data-sidebar-account');

    assert.match(accountClasses, /\bshrink-0\b/);
});

test('the sidebar shell has no bottom spacing', () => {
    const shellClasses = divClassesFor('data-sidebar-shell');

    assert.doesNotMatch(shellClasses, /\b(?:p|m)b-/);
});
