import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const sidebar = readFileSync(
    new URL('../../resources/js/components/AppSidebar.vue', import.meta.url),
    'utf8',
);
const mobileSidebar = readFileSync(
    new URL(
        '../../resources/js/components/AppSidebarHeader.vue',
        import.meta.url,
    ),
    'utf8',
);
const sidebarLayout = readFileSync(
    new URL(
        '../../resources/js/layouts/app/AppSidebarLayout.vue',
        import.meta.url,
    ),
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

test('admin navigation sits above Controls Room outside the scrolling region', () => {
    const scrollEnd = sidebar.indexOf(
        '</nav>',
        sidebar.indexOf('data-sidebar-scroll'),
    );
    const accountStart = sidebar.indexOf('data-sidebar-account');
    const admin = sidebar.indexOf('data-sidebar-admin');
    const controlsRoom = sidebar.indexOf('<DropdownMenu>', accountStart);

    assert.ok(scrollEnd >= 0);
    assert.ok(admin > accountStart);
    assert.ok(admin > scrollEnd);
    assert.ok(controlsRoom > admin);
    assert.doesNotMatch(sidebar, /items\.push\(\{\s+key: 'admin'/);
});

test('mobile navigation keeps admin with the settings controls', () => {
    const admin = mobileSidebar.indexOf('data-mobile-sidebar-admin');
    const controlsRoom = mobileSidebar.indexOf('<DropdownMenu>', admin);

    assert.ok(admin >= 0);
    assert.ok(controlsRoom > admin);
    assert.doesNotMatch(mobileSidebar, /items\.push\(\{\s+key: 'admin'/);
});

test('the shared page scroller keeps bottom spacing at every breakpoint', () => {
    assert.match(sidebarLayout, /overflow-y-auto pb-4/);
    assert.doesNotMatch(sidebarLayout, /lg:pb-0/);
});

test('the sidebar shell has no bottom spacing', () => {
    const shellClasses = divClassesFor('data-sidebar-shell');

    assert.doesNotMatch(shellClasses, /\b(?:p|m)b-/);
});
