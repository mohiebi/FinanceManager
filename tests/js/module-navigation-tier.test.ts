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

test('module settings use Miles activation instead of Pro badges', () => {
    // The nav no longer knows what a paid tier is: no crown, no tier label, no
    // branch on 'pro'. What is left says only whether a module is switched on.
    for (const source of [sidebar, mobileSidebar]) {
        assert.doesNotMatch(source, /item\.tier === 'pro'/);
        assert.doesNotMatch(source, /<Crown/);
        assert.doesNotMatch(source, /modules\.tiers\./);
        assert.match(source, /v-if="item\.state !== 'enabled'"/);
    }

    // Unlocking is priced in Miles on the settings page, which is where the
    // decision actually gets made.
    assert.match(modulesPage, /module\.activation_cost > 0/);
    assert.match(modulesPage, /pendingActivation\.unlock_features/);
    assert.match(modulesPage, /module\.can_afford/);
    assert.match(modulesPage, /pendingActivation\.shortfall/);
    assert.doesNotMatch(modulesPage, /modules\.tiers\.pro/);
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
