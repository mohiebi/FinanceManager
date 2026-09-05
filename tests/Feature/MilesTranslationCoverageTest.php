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
