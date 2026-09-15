<?php

test('every locale has the same translation keys and placeholders', function () {
    $flatten = function (array $translations, string $prefix = '') use (&$flatten): array {
        $flattened = [];

        foreach ($translations as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flattened = [...$flattened, ...$flatten($value, $path)];

                continue;
            }

            $flattened[$path] = (string) $value;
        }

        return $flattened;
    };

    $placeholders = function (string $translation): array {
        preg_match_all('/(?<!:):([a-z_][a-z0-9_]*)|\{([a-z_][a-z0-9_]*)\}/i', $translation, $matches);

        $placeholders = array_values(array_unique(array_filter([
            ...$matches[1],
            ...$matches[2],
        ])));
        sort($placeholders);

        return $placeholders;
    };

    $englishFiles = collect(glob(resource_path('lang/en/*.php')))
        ->mapWithKeys(fn (string $path): array => [basename($path) => $path]);

    foreach (['de', 'fa'] as $locale) {
        $localeFiles = collect(glob(resource_path("lang/{$locale}/*.php")))
            ->mapWithKeys(fn (string $path): array => [basename($path) => $path]);

        expect($localeFiles->keys()->sort()->values()->all())
            ->toBe($englishFiles->keys()->sort()->values()->all(), "{$locale} translation files differ from English");

        foreach ($englishFiles as $file => $englishPath) {
            $english = $flatten(require $englishPath);
            $translated = $flatten(require $localeFiles[$file]);

            $englishKeys = array_keys($english);
            $translatedKeys = array_keys($translated);
            sort($englishKeys);
            sort($translatedKeys);

            expect($translatedKeys)->toBe($englishKeys, "{$locale}/{$file} keys differ from English");

            foreach ($english as $key => $value) {
                expect($placeholders($translated[$key]))
                    ->toBe($placeholders($value), "{$locale}/{$file}: {$key} uses different placeholders");
            }
        }
    }
});
