<?php

use App\Enums\Milestone;
use App\Support\FrontendLocalization;

/**
 * Milestone copy lives in two namespaces and the enum keeps growing, so a new
 * case ships its key straight to the screen unless something checks. Both of
 * these rendered raw (`gamification.moments.first_bill.title`) in production
 * before this existed.
 */
test('every milestone has moment and notification copy in every locale', function () {
    $missing = [];

    foreach (FrontendLocalization::locales() as $locale) {
        foreach (Milestone::cases() as $milestone) {
            foreach (['gamification.moments', 'notifications.milestones'] as $namespace) {
                foreach (['title', 'body'] as $part) {
                    $key = "{$namespace}.{$milestone->value}.{$part}";

                    if (trans($key, [], $locale) === $key) {
                        $missing[] = "{$locale}: {$key}";
                    }
                }
            }
        }
    }

    expect($missing)->toBe([]);
});

test('every cosmetic in the catalogue has a translated name and type', function () {
    $missing = [];

    foreach (FrontendLocalization::locales() as $locale) {
        foreach (config('miles.cosmetics') as $key => $cosmetic) {
            foreach (["miles.cosmetic_labels.{$key}", "miles.cosmetic_types.{$cosmetic['type']}"] as $translationKey) {
                if (trans($translationKey, [], $locale) === $translationKey) {
                    $missing[] = "{$locale}: {$translationKey}";
                }
            }
        }
    }

    expect($missing)->toBe([]);
});

/**
 * Keys are added and removed by hand across three files, so one locale keeping
 * a key another dropped shows the reader a raw `miles.whatever` on that page.
 */
test('the miles catalogue holds the same keys in every locale', function () {
    $flatten = function (array $messages, string $prefix = '') use (&$flatten): array {
        $keys = [];

        foreach ($messages as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            $keys = array_merge($keys, is_array($value) ? $flatten($value, $path) : [$path]);
        }

        return $keys;
    };

    $reference = null;

    foreach (FrontendLocalization::locales() as $locale) {
        $keys = $flatten(trans('miles', [], $locale));
        sort($keys);

        if ($reference === null) {
            $reference = $keys;

            continue;
        }

        expect($keys)->toBe($reference, "miles keys differ in {$locale}");
    }

    expect($reference)->not->toBeEmpty();
});
