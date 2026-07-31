<?php

use App\Support\FrontendLocalization;

/**
 * Placeholder syntax has to match whoever renders the string.
 *
 * Laravel's `trans()` interpolates `:name`; vue-i18n interpolates `{name}`. A
 * file read by the browser that still uses colons renders the placeholder
 * literally — the user sees ":month logbook" — and nothing fails, which is why
 * this needs a test rather than a code review.
 */

/** Files shipped to the browser via FrontendLocalization::messages(). */
function clientRenderedGroups(): array
{
    return ['gamification'];
}

test('client-rendered translations use vue-i18n placeholder syntax', function () {
    foreach (FrontendLocalization::locales() as $locale) {
        foreach (clientRenderedGroups() as $group) {
            $path = resource_path("lang/{$locale}/{$group}.php");
            $contents = file_get_contents($path);

            // A colon followed by a word, not preceded by another colon (which
            // would be a `::` namespace) and not a clock time like 21:00.
            preg_match_all('/(?<![\w:]):([a-z_]{2,})/', $contents, $matches);

            expect($matches[1])->toBeEmpty(
                "{$locale}/{$group}.php uses Laravel :placeholders, but this file is rendered by vue-i18n"
            );
        }
    }
});

test('every locale defines the same gamification keys', function () {
    $flatten = function (array $messages, string $prefix = '') use (&$flatten): array {
        $keys = [];

        foreach ($messages as $key => $value) {
            $keys = is_array($value)
                ? [...$keys, ...$flatten($value, "{$prefix}{$key}.")]
                : [...$keys, "{$prefix}{$key}"];
        }

        return $keys;
    };

    $english = $flatten(require resource_path('lang/en/gamification.php'));

    foreach (['fa', 'de'] as $locale) {
        $keys = $flatten(require resource_path("lang/{$locale}/gamification.php"));

        // A missing key falls back to English rather than failing, so a gap here
        // is invisible until a Persian user reads an English sentence.
        expect(array_diff($english, $keys))->toBeEmpty("{$locale} is missing gamification keys")
            ->and(array_diff($keys, $english))->toBeEmpty("{$locale} has gamification keys English does not");
    }
});
