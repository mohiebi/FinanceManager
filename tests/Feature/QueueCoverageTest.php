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

/**
 * The queues production actually drains.
 *
 * Supervisor runs one `queue:work` per program inside the app container, so the
 * production list is the union of their --queue flags. This used to be described
 * as living outside the repo — it does not, and leaving it unchecked is how
 * `default` and `billing` came to have no worker at all while the dev listener
 * covered both and this file stayed green.
 *
 * @return array<int, string>
 */
function productionWorkedQueueNames(): array
{
    preg_match_all(
        '/queue:work\s+--queue=([a-z_,-]+)/i',
        (string) file_get_contents(base_path('docker/supervisord.conf')),
        $matches,
    );

    return collect($matches[1])
        ->flatMap(fn (string $queues): array => explode(',', $queues))
        ->unique()
        ->values()
        ->all();
}

/** The longest any job may occupy a worker, in seconds. */
function longestJobTimeout(): int
{
    return collect(File::allFiles(app_path('Jobs')))
        ->map(function ($file): int {
            $class = 'App\\Jobs\\'.$file->getFilenameWithoutExtension();

            if (! class_exists($class)) {
                return 0;
            }

            $job = (new ReflectionClass($class))->newInstanceWithoutConstructor();

            return method_exists($job, 'maximumSeconds')
                ? $job->maximumSeconds()
                : (int) ((new ReflectionClass($class))->getDefaultProperties()['timeout'] ?? 0);
        })
        ->max() ?? 0;
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

test('production drains every queue the local listener does', function () {
    $production = productionWorkedQueueNames();

    $unworked = collect([...queueNamesUsedByCode(), 'default'])
        ->unique()
        ->reject(fn (string $queue): bool => in_array($queue, $production, true))
        ->values()
        ->all();

    expect($unworked)->toBe([]);
});

test('a job cannot outlive the queue retry window', function () {
    // Past retry_after the job is visible again while the first worker is still
    // running it. On a paid provider call that is a second charge, not a retry.
    expect(config('queue.connections.database.retry_after'))
        ->toBeGreaterThan(longestJobTimeout());
});
