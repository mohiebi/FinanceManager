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
    assert.match(modulesPage, /module\.activation_cost > 0/);
    assert.match(modulesPage, /pendingActivation\.unlock_features/);
    assert.match(modulesPage, /module\.can_afford/);
    assert.match(modulesPage, /pendingActivation\.shortfall/);
    assert.doesNotMatch(modulesPage, /modules\.tiers\.pro/);
    assert.doesNotMatch(modulesPage, /<Crown/);
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

test('flight vocabulary only appears when the user asked for it', () => {
    const nav = readFileSync(
        new URL(
            '../../resources/js/composables/useModuleNav.ts',
            import.meta.url,
        ),
        'utf8',
    );
    const header = readFileSync(
        new URL(
            '../../resources/js/components/AppSidebarHeader.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const en = readFileSync(
        new URL('../../resources/lang/en/navigation.php', import.meta.url),
        'utf8',
    );

    // The standard key is what a user who never opted in sees, so it must not
    // be the flight name; the variant beside it carries that.
    assert.match(en, /'flight_log' => 'Activity'/);
    assert.match(en, /'flight_log_subtitle' => 'Flight log'/);

    // Both places that render the name have to pass the variant through, or the
    // preference is silently ignored wherever one of them was missed.
    for (const source of [nav, header]) {
        assert.match(
            source,
            /'navigation\.flight_log',\s*'navigation\.flight_log_subtitle'/,
        );
    }
});
