<?php

use Illuminate\Support\Facades\File;

/**
 * Every queue the app pushes onto must be one a worker consumes.
 *
 * Written because nothing did. `queue:listen` with no --queue only drains
 * `default`, while every job here calls onQueue() with a name of its own — so
 * auth codes, bill reminders, streak nudges and Telegram messages all queued up
 * and none of them ever ran. Six of them were still sitting in the table.
 */
function queueNamesUsedByCode(): array
{
    $names = [];

    foreach (File::allFiles(app_path()) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        preg_match_all(
            "/onQueue\(\s*'([a-z_-]+)'\s*\)/i",
            (string) file_get_contents($file->getPathname()),
            $matches,
        );

        $names = [...$names, ...$matches[1]];
    }

    return array_values(array_unique($names));
}

function workedQueueNames(): array
{
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
    $dev = implode(' ', $composer['scripts']['dev']);

    if (! preg_match('/--queue=([a-z_,-]+)/i', $dev, $matches)) {
        return [];
    }

    return explode(',', $matches[1]);
}

test('every queue a job is pushed onto is one the worker drains', function () {
    $worked = workedQueueNames();

    $unworked = collect(queueNamesUsedByCode())
        ->reject(fn (string $queue): bool => in_array($queue, $worked, true))
        ->values()
        ->all();

    expect($unworked)->toBe([]);
});

test('the worker also drains the default queue', function () {
    // Anything dispatched without an explicit onQueue lands here, including
    // framework-internal work and any job added later that forgets to pick one.
    expect(workedQueueNames())->toContain('default');
});
