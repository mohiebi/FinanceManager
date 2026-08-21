import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

function source(path: string): string {
    return readFileSync(new URL(path, import.meta.url), 'utf8');
}

const moduleNavigation = source(
    '../../resources/js/composables/useModuleNav.ts',
);
const sidebar = source('../../resources/js/components/AppSidebar.vue');
const mobileSidebar = source(
    '../../resources/js/components/AppSidebarHeader.vue',
);
const modulesPage = source('../../resources/js/pages/settings/Modules.vue');

test('module badges use the product tier instead of entitlement state', () => {
    assert.match(moduleNavigation, /tier: state\.tier/);
    assert.match(sidebar, /modules\.tiers\.\$\{item\.tier\}/);
    assert.match(mobileSidebar, /modules\.tiers\.\$\{item\.tier\}/);
    assert.doesNotMatch(
        sidebar,
        /modules\.tiers\.\$\{item\.state === 'locked'/,
    );
    assert.doesNotMatch(
        mobileSidebar,
        /modules\.tiers\.\$\{item\.state === 'locked'/,
    );
    assert.match(sidebar, /item\.tier === 'pro' \|\|/);
    assert.match(mobileSidebar, /item\.tier === 'pro' \|\|/);
    assert.match(sidebar, /<Crown/);
    assert.match(mobileSidebar, /<Crown/);
    assert.match(modulesPage, /v-if="module\.tier === 'pro'"/);
    assert.match(modulesPage, /modules\.tiers\.pro/);
    assert.match(modulesPage, /<Crown/);
});

test('a locked module links to its own paywall, not to the modules page', () => {
    // Sending someone who wanted to buy the feature to a settings page put a
    // locked row and a switch that refuses to move in front of them. The
    // feature's own route is where its paywall lives.
    assert.match(moduleNavigation, /const locked = !state\.may_use;/);
    assert.match(
        moduleNavigation,
        /href: locked \? entry\.href : editModules\(\)/,
    );
    assert.match(moduleNavigation, /state: locked \? 'locked' : 'promo'/);
});

test('a promo module still points at the switch it needs', () => {
    // It is owned and merely switched off, so the modules page is the right
    // destination — and standing on that page must not light up every promo row.
    for (const shell of [sidebar, mobileSidebar]) {
        assert.match(
            shell,
            /item\.state !== 'promo' && isCurrentUrl\(item\.href\)/,
        );
    }
});
