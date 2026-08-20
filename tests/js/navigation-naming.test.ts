import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

function source(path: string): string {
    return readFileSync(new URL(path, import.meta.url), 'utf8');
}

const moduleNavigation = source(
    '../../resources/js/composables/useModuleNav.ts',
);
const naming = source('../../resources/js/composables/useNavigationNaming.ts');
const sidebar = source('../../resources/js/components/AppSidebar.vue');
const mobileSidebar = source(
    '../../resources/js/components/AppSidebarHeader.vue',
);
const modules = source('../../resources/js/pages/settings/Modules.vue');
const settingsLayout = source('../../resources/js/layouts/settings/Layout.vue');
const goals = source('../../resources/js/pages/Goals.vue');
const budgets = source('../../resources/js/pages/Budgets.vue');

test('navigation resolves exactly one naming vocabulary from the user preference', () => {
    assert.match(naming, /flightTerminologyEnabled/);
    // Opt-in: an absent prop must resolve to standard names, not flight ones.
    assert.match(naming, /page\.props\.flightTerminologyEnabled === true/);
    assert.match(moduleNavigation, /useNavigationNaming/);
    assert.doesNotMatch(moduleNavigation, /subtitle\s*:/);
    assert.doesNotMatch(sidebar, /item\.subtitle/);
    assert.doesNotMatch(mobileSidebar, /item\.subtitle/);
    assert.match(modules, /moduleLabel/);
    assert.match(modules, /navigation\.budgets_subtitle/);
});

test('mapped page headings do not render both names together', () => {
    assert.match(settingsLayout, /navigationName/);
    assert.match(goals, /navigationName/);
    assert.match(budgets, /navigationName/);
    assert.doesNotMatch(settingsLayout, /\(\{\{\s*t\('settings\.title'/);
    assert.doesNotMatch(goals, /\(\{\{\s*t\('gamification\.goals\.page_title'/);
    assert.doesNotMatch(budgets, /\(\{\{\s*t\('navigation\.budgets'/);
});
