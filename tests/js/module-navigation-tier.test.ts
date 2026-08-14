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
