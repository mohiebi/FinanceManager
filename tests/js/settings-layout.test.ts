import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const settingsPages = {
    categories: readSettingsPage('Categories.vue'),
    assets: readSettingsPage('Assets.vue'),
    aiConnections: readSettingsPage('AiConnections.vue'),
    appearance: readSettingsPage('Appearance.vue'),
};

function readSettingsPage(file: string): string {
    return readFileSync(
        new URL(`../../resources/js/pages/settings/${file}`, import.meta.url),
        'utf8',
    );
}

function sectionCount(source: string): number {
    return source.match(/<SettingsSection(?:\s|>)/g)?.length ?? 0;
}

test('remaining settings pages use the shared section surface', () => {
    for (const source of Object.values(settingsPages)) {
        assert.match(
            source,
            /import SettingsSection from '@\/components\/settings\/SettingsSection\.vue';/,
        );
        assert.doesNotMatch(source, /class="settings-card/);
    }
});

test('long settings pages split distinct concerns into separate cards', () => {
    assert.equal(sectionCount(settingsPages.categories), 2);
    assert.equal(sectionCount(settingsPages.assets), 2);
    assert.equal(sectionCount(settingsPages.aiConnections), 3);
    assert.equal(sectionCount(settingsPages.appearance), 1);
});
