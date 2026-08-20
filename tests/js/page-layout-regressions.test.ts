import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

function source(path: string): string {
    return readFileSync(new URL(path, import.meta.url), 'utf8');
}

const assessment = source('../../resources/js/pages/Advisor/Assessment.vue');
const appLayout = source('../../resources/js/layouts/app/AppSidebarLayout.vue');
const styles = source('../../resources/css/app.css');
const preferences = source('../../resources/js/pages/settings/Preferences.vue');
const appHeader = source('../../resources/js/components/AppSidebarHeader.vue');

test('advisor assessment owns its bottom spacing and uses the shell background', () => {
    assert.match(appLayout, /app-page-scroll/);
    assert.match(assessment, /data-app-flush-bottom/);
    assert.match(assessment, /bg-background/);
    assert.match(assessment, /min-h-\[calc\(100svh-72px\)\]/);
    assert.match(assessment, /lg:min-h-\[calc\(100svh-92px\)\]/);
    assert.doesNotMatch(assessment, /bg-\[#0d0f0f\]/);
    // Descendant rather than direct-child: the 1440px content-width wrapper
    // in AppSidebarLayout sits between the scroller and every page's root
    // element, one level deeper than a direct child.
    assert.match(styles, /\.app-page-scroll:has\(\[data-app-flush-bottom\]\)/);
    assert.match(styles, /padding-bottom:\s*0/);
});

test('the time zone select matches the other preference control widths', () => {
    assert.doesNotMatch(preferences, /sm:w-\[320px\]/);
    assert.match(preferences, /id="timezone"[\s\S]*?sm:w-\[220px\]/);
});

test('the application header remains visible while settings content scrolls', () => {
    assert.match(appHeader, /class="sticky top-0 z-40/);
    assert.doesNotMatch(appHeader, /lg:static/);
});
