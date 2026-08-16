import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const settingsPages = {
    categories: readSettingsPage('Categories.vue'),
    assets: readSettingsPage('Assets.vue'),
    aiConnections: readSettingsPage('AiConnections.vue'),
    appearance: readSettingsPage('Appearance.vue'),
    modules: readSettingsPage('Modules.vue'),
    notifications: readSettingsPage('Notifications.vue'),
    preferences: readSettingsPage('Preferences.vue'),
    profile: readSettingsPage('Profile.vue'),
    security: readSettingsPage('Security.vue'),
    telegram: readSettingsPage('Telegram.vue'),
    billing: readSettingsPage('Billing.vue'),
};

const shell = source('../../resources/js/layouts/settings/Layout.vue');
const section = source(
    '../../resources/js/components/settings/SettingsSection.vue',
);
const saveBar = source(
    '../../resources/js/components/settings/SettingsSaveBar.vue',
);
const styles = source('../../resources/css/app.css');

function source(path: string): string {
    return readFileSync(new URL(path, import.meta.url), 'utf8');
}

function readSettingsPage(file: string): string {
    return source(`../../resources/js/pages/settings/${file}`);
}

function sectionCount(source: string): number {
    return source.match(/<SettingsSection(?:\s|>)/g)?.length ?? 0;
}

test('every settings page composes the shared section surface', () => {
    for (const [name, page] of Object.entries(settingsPages)) {
        assert.match(
            page,
            /import SettingsSection from '@\/components\/settings\/SettingsSection\.vue';/,
            `${name} should import SettingsSection`,
        );
        assert.ok(
            sectionCount(page) > 0,
            `${name} should render at least one SettingsSection`,
        );
    }

    // One definition of the card surface, not two. The `.settings-card` rule
    // existed for pages not yet split into sections; none are left. Matched on
    // the rule rather than the name, so the comment explaining its removal
    // does not fail the test that enforces it.
    assert.doesNotMatch(styles, /\.settings-card\s*\{/);
});

test('long settings pages split distinct concerns into separate cards', () => {
    assert.equal(sectionCount(settingsPages.categories), 2);
    assert.equal(sectionCount(settingsPages.assets), 2);
    assert.equal(sectionCount(settingsPages.aiConnections), 3);
    assert.equal(sectionCount(settingsPages.appearance), 1);
    assert.equal(sectionCount(settingsPages.modules), 2);
});

test('the shell names the active section and owns the only h1', () => {
    // Two pages used to hide an `sr-only` <h1> under the shell's own, so the
    // word "Settings" was announced twice and the section name never.
    assert.match(shell, /activeItem\?\.title/);
    assert.match(shell, /<h1/);

    for (const [name, page] of Object.entries(settingsPages)) {
        assert.doesNotMatch(page, /<h1/, `${name} should not add a second h1`);
    }
});

test('the shell offers a scrollable rail below lg instead of a ten-link stack', () => {
    assert.match(shell, /lg:hidden/);
    assert.match(shell, /overflow-x-auto/);
    // The sidebar is the desktop half of the same pair, so it has to be hidden
    // below lg or both render at once.
    assert.match(shell, /<aside\s+class="hidden/);
});

test('settings navigation clears the 44px touch target', () => {
    const navTargets = shell.match(/min-h-11/g) ?? [];

    assert.ok(
        navTargets.length >= 2,
        'both the rail and the sidebar links need a 44px floor',
    );
});

test('one save control serves every settings form', () => {
    for (const page of [
        settingsPages.profile,
        settingsPages.security,
        settingsPages.preferences,
    ]) {
        assert.match(
            page,
            /import SettingsSaveBar from '@\/components\/settings\/SettingsSaveBar\.vue';/,
        );
        // The hand-rolled "Saved" flash each of these carried is the give-away
        // for a page that has drifted back off the shared control.
        assert.doesNotMatch(page, /v-show="[\w.]*recentlySuccessful"/);
    }
});

test('preferences commits the whole form from a bar that stays in view', () => {
    // Language, calendar, time zone and currency live in the first card and
    // used to have no save button at all — the only one sat in the footer of
    // the second card, under a heading about terminology.
    assert.match(settingsPages.preferences, /<SettingsSaveBar\s+sticky/);
    assert.match(settingsPages.preferences, /:dirty="form\.isDirty"/);
    assert.doesNotMatch(settingsPages.preferences, /#footer/);
    assert.match(saveBar, /sticky bottom-4/);
});

test('the save bar announces its result without hijacking the reader', () => {
    assert.match(saveBar, /role="status"/);
    assert.match(saveBar, /aria-live="polite"/);
    assert.match(saveBar, /motion-reduce:transition-none/);
});

test('sections can lead with an icon and carry their own actions', () => {
    assert.match(section, /icon\?: Component/);
    assert.match(section, /\$slots\.actions/);
    // "Mark all read" under a full inbox is the one place nobody scrolls to.
    assert.match(settingsPages.notifications, /#actions/);
});

test('the shared field style replaces the per-page utility strings', () => {
    assert.match(styles, /\.settings-input\b/);
    assert.match(styles, /min-height: 2\.75rem/);

    for (const page of [settingsPages.profile, settingsPages.security]) {
        assert.match(page, /class="settings-input"/);
        assert.doesNotMatch(page, /placeholder:text-\[#686868\]/);
    }
});

test('billing decides the plan before it asks for a code', () => {
    const billing = settingsPages.billing;

    // Plan cards used to fire the purchase on click, with the coupon box below
    // them — so "Choose" was the first control you reached and the code was
    // something you found afterwards, if you scrolled.
    assert.match(billing, /@click="selectPlan\(plan\)"/);
    assert.doesNotMatch(billing, /@click="choosePlan\(plan\)"/);

    // The plan grid and the checkout step are alternatives, never both at once.
    assert.match(billing, /v-if="!pending && !selectedPlan"/);
    assert.match(billing, /v-if="!pending && selectedPlan && checkout"/);

    // The code, the total and the confirm button all live in the second step.
    const checkoutStart = billing.indexOf('billing.checkout.heading');
    const couponField = billing.indexOf('id="coupon"');
    const total = billing.indexOf('billing.checkout.total');

    assert.ok(checkoutStart > 0 && couponField > checkoutStart);
    assert.ok(total > couponField, 'the total is read after the code is added');
});

test('a coupon covering the price hides the rail and says nothing is owed', () => {
    const billing = settingsPages.billing;

    assert.match(billing, /billing\.checkout\.nothing_to_pay_title/);
    // No chain is involved in a zero transfer, so there is no wallet to pick.
    assert.match(billing, /v-if="nothingToPay"/);
    assert.match(billing, /<div v-else class="mt-4">/);
    // And the button says which of the two things it is about to do.
    assert.match(billing, /billing\.checkout\.confirm_free/);
    assert.match(billing, /billing\.checkout\.confirm_paid/);
});

test('an outright activation is confirmed in a dialog, not a flash strip', () => {
    const billing = settingsPages.billing;

    // Its own prop, so the page never has to match on a translated sentence.
    assert.match(billing, /activated: ActivationReceipt \| null/);
    assert.match(billing, /aria-labelledby="billing-activated-title"/);
    assert.match(billing, /billing\.activated\.title/);
    // Dismissing lands on a clean billing page rather than the same render.
    assert.match(billing, /router\.visit\(billingEdit\(\)\.url\)/);
});

test('the history lists coupon redemptions beside payments', () => {
    const billing = settingsPages.billing;

    // A code covering the whole price opens no intent, so a history built from
    // payments alone showed nothing for it — leaving the intent the buyer had
    // cancelled to go and use the code as the only trace of a purchase that
    // actually succeeded.
    assert.match(billing, /history: HistoryEntry\[\]/);
    assert.doesNotMatch(billing, /payments: PaymentRecord\[\]/);
    assert.match(billing, /v-for="entry in history"/);
    assert.match(billing, /entry\.kind === 'coupon'/);
    // The poll has to name the renamed prop or it reloads nothing.
    assert.match(billing, /only: \['pending', 'history', 'subscription'\]/);
});

test('notification preferences sit above the inbox', () => {
    const page = settingsPages.notifications;
    const preferences = page.indexOf('notifications.preferences_heading');
    const inbox = page.indexOf('notifications.page_description');

    // The switches are the short block; the inbox can run to a hundred rows and
    // would push them off the first screen.
    assert.ok(preferences > 0 && inbox > preferences);
});

test('notifications are filterable, grouped, and typed by icon', () => {
    const page = settingsPages.notifications;

    assert.match(
        page,
        /notifications\.filter\.\$\{option\}|filter\.\${option}/,
    );
    assert.match(page, /notifications\.groups\.\$\{group\.key\}/);
    assert.match(page, /const typeStyles: Record</);
    // A kind this build has not heard of still renders a row.
    assert.match(page, /fallbackStyle/);
});

test('a read notification is not a button that does nothing', () => {
    const page = settingsPages.notifications;

    assert.match(page, /notification\.read_at \? 'div' : 'button'/);
    // Fading the whole row took the body and timestamp under 4.5:1.
    assert.doesNotMatch(page, /notification\.read_at \? 'opacity-60' : ''/);
    // The unread dot carries real text; aria-label on a bare span is ignored.
    assert.match(
        page,
        /<span class="sr-only">\{\{\s*\n?\s*t\('notifications\.unread'\)/,
    );
});

test('both module confirmation dialogs identify themselves', () => {
    const labelledDialogs =
        settingsPages.modules.match(/aria-labelledby="module-/g) ?? [];

    assert.equal(labelledDialogs.length, 2);
    assert.match(
        settingsPages.modules,
        /aria-labelledby="module-disable-title"/,
    );
});
