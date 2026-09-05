import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

function source(path: string): string {
    return readFileSync(new URL(path, import.meta.url), 'utf8');
}

const assessment = source('../../resources/js/pages/Advisor/Assessment.vue');
const appLayout = source('../../resources/js/layouts/app/AppSidebarLayout.vue');
const appContent = source('../../resources/js/components/AppContent.vue');
const styles = source('../../resources/css/app.css');
const preferences = source('../../resources/js/pages/settings/Preferences.vue');
const appHeader = source('../../resources/js/components/AppSidebarHeader.vue');
const emailAuth = source('../../resources/js/pages/auth/EmailAuth.vue');
const twoFactor = source(
    '../../resources/js/pages/auth/TwoFactorChallenge.vue',
);

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

test('the app content is an Inertia-managed scroll region', () => {
    assert.match(
        appContent,
        /<SidebarInset[\s\S]*?v-if="props\.variant === 'sidebar'"[\s\S]*?scroll-region/,
    );
});

test('successful auth transitions do not preserve the auth page instance', () => {
    // verifySignup joined the list because it is the last step of a signup
    // and ends logged in, exactly like the three already covered.
    //
    // 'errors' rather than false: a flat false drops the instance on the failure
    // path too, and with it the <Form> holding the validation errors — a wrong
    // password or a rejected signup re-rendered the page with nothing to show.
    // Inertia resolves 'errors' against the response, so a response carrying
    // errors keeps the form and every other one still hands the app a fresh
    // instance.
    for (const action of [
        'login',
        'completeSignup',
        'verifyRecovery',
        'verifySignup',
    ]) {
        assert.match(
            emailAuth,
            new RegExp(
                `${action}\\.form\\(\\)"[\\s\\S]*?:options="\\{ preserveState: 'errors' \\}"`,
            ),
        );
    }
});

test('the two-factor challenge is an auth transition too', () => {
    // For an account with 2FA this form — not the password step — is the request
    // that ends logged in, so it needs the same treatment. Both branches submit
    // it: the authenticator code and the recovery code.
    const forms = twoFactor.match(
        /v-bind="store\.form\(\)"\s*\n\s*:options="\{ preserveState: 'errors' \}"/g,
    );

    assert.equal(forms?.length, 2);
});

test('every panel that scrolls inside the page shares one scrollbar', () => {
    // Firefox reads the two standard properties; Chrome and Safari still only
    // honour the ::-webkit- rules, so dropping either half leaves half the
    // users looking at an OS-default slab on a dark card.
    assert.match(styles, /\.app-scroll-thin\s*\{[^}]*scrollbar-width:\s*thin/);
    assert.match(styles, /\.app-scroll-thin::-webkit-scrollbar\s*\{/);
    assert.match(styles, /\.app-scroll-thin::-webkit-scrollbar-thumb\s*\{/);

    // The rules used to live in AppSidebar's scoped block, where nothing else
    // could reach them. Anything left behind there would be a second answer.
    const sidebar = source('../../resources/js/components/AppSidebar.vue');
    assert.match(sidebar, /app-scroll-thin/);
    assert.doesNotMatch(sidebar, /sidebar-nav-scroll/);
    assert.doesNotMatch(sidebar, /::-webkit-scrollbar/);

    for (const path of [
        '../../resources/js/pages/Miles/Index.vue',
        '../../resources/js/components/NotificationBell.vue',
        '../../resources/js/components/admin/AdminCustomerDrawer.vue',
        '../../resources/js/components/transactions/TransactionDialog.vue',
        '../../resources/js/layouts/app/AppSidebarLayout.vue',
    ]) {
        assert.match(source(path), /app-scroll-thin/, path);
    }
});
