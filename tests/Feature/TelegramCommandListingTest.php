<?php

use App\Telegraph\TelegramHandler;

/**
 * The settings page advertises a list of bot commands by hand.
 *
 * It had drifted: the bot grew bills, portfolio, no-spend days, help and a
 * day-picking report, and the page still listed the original seven. A command
 * that does not exist is a broken promise; one that exists and is not listed is
 * a feature nobody finds.
 */
function advertisedCommands(): array
{
    $page = (string) file_get_contents(resource_path('js/pages/settings/Telegram.vue'));

    preg_match_all("/cmd: '\/([a-z_]+)'/", $page, $matches);

    return array_values(array_unique($matches[1]));
}

function handlerCommands(): array
{
    return collect((new ReflectionClass(TelegramHandler::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->filter(fn (ReflectionMethod $method): bool => $method->class === TelegramHandler::class)
        ->map(fn (ReflectionMethod $method): string => $method->getName())
        ->values()
        ->all();
}

test('every command the page advertises is one the bot handles', function () {
    $handled = handlerCommands();

    $unhandled = collect(advertisedCommands())
        ->reject(fn (string $command): bool => in_array($command, $handled, true))
        ->values()
        ->all();

    expect($unhandled)->toBe([]);
});

test('every command on the bot menu is advertised on the settings page', function () {
    // The keyboard is what a linked user is actually offered, so it is the set
    // the page has to keep up with. Wizard steps and callback-only handlers
    // (pick_category, confirm_tx, …) are not commands anyone types.
    $onMenu = [
        'add_cost',
        'add_income',
        'list',
        'add_investment',
        'add_bill',
        'list_bills',
        'portfolio',
        'no_spend',
        'report_daily',
        'budget',
        'report_week',
        'report_month',
    ];

    $missing = collect($onMenu)
        ->reject(fn (string $command): bool => in_array($command, advertisedCommands(), true))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

test('every advertised command has a description in all three locales', function () {
    $missing = [];

    foreach (['en', 'fa', 'de'] as $locale) {
        $descriptions = require resource_path("lang/{$locale}/settings.php");

        foreach (advertisedCommands() as $command) {
            if (! isset($descriptions['telegram']['commands'][$command])) {
                $missing[] = "{$locale}.{$command}";
            }
        }
    }

    expect($missing)->toBe([]);
});
