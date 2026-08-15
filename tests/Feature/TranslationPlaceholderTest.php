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
    // advisor joined this list after `assessment.step` shipped as
    // 'Section :current of :total' and rendered exactly that, literally, on
    // every page of the assessment.
    return ['gamification', 'billing', 'advisor'];
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

test('every locale defines the same client-rendered keys', function () {
    $flatten = function (array $messages, string $prefix = '') use (&$flatten): array {
        $keys = [];

        foreach ($messages as $key => $value) {
            $keys = is_array($value)
                ? [...$keys, ...$flatten($value, "{$prefix}{$key}.")]
                : [...$keys, "{$prefix}{$key}"];
        }

        return $keys;
    };

    // Loops the same list as the placeholder check above rather than naming one
    // group, so adding a browser-rendered file gets both guards at once.
    foreach (clientRenderedGroups() as $group) {
        $english = $flatten(require resource_path("lang/en/{$group}.php"));

        foreach (['fa', 'de'] as $locale) {
            $keys = $flatten(require resource_path("lang/{$locale}/{$group}.php"));

            // A missing key falls back to English rather than failing, so a gap
            // here is invisible until a Persian user reads an English sentence.
            expect(array_diff($english, $keys))->toBeEmpty("{$locale} is missing {$group} keys")
                ->and(array_diff($keys, $english))->toBeEmpty("{$locale} has {$group} keys English does not");
        }
    }
});

test('every advisor assessment option has a translation', function () {
    $definition = json_decode(
        file_get_contents(resource_path('js/lib/advisor/scoring-v1.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $translations = require resource_path('lang/en/advisor.php');
    $translatedOptions = array_keys($translations['options']);

    foreach ($definition['questions'] as $questionKey => $question) {
        foreach (['options', 'proportion_options', 'speed_options'] as $optionGroup) {
            $missingOptions = array_diff($question[$optionGroup] ?? [], $translatedOptions);

            expect($missingOptions)->toBeEmpty(
                "{$questionKey}.{$optionGroup} contains options without advisor translations"
            );
        }
    }
});
