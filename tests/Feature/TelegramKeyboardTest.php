<?php

use App\Telegraph\TelegramHandler;

test('telegram main keyboard groups related actions into rows', function () {
    $handler = new TelegramHandler;
    $method = new ReflectionMethod($handler, 'mainKeyboard');
    $method->setAccessible(true);

    $keyboard = $method->invoke($handler)->toArray();

    expect(collect($keyboard)->map(fn (array $row): array => array_column($row, 'text'))->all())
        ->toBe([
            ['Add cost', 'Add income'],
            ['Last transactions', 'Add investment'],
            ['Today report', 'Week report', 'Month report'],
        ]);
});

test('telegram transaction wizard offers currency choices', function () {
    $handler = new TelegramHandler;
    $method = new ReflectionMethod($handler, 'currencyKeyboard');
    $method->setAccessible(true);

    $keyboard = $method->invoke($handler)->toArray();

    expect(collect($keyboard)->flatten(1)->map(fn (array $button): array => [
        'text' => $button['text'],
        'callback_data' => $button['callback_data'],
    ])->all())->toBe([
        ['text' => 'TOMAN', 'callback_data' => 'action:pick_currency;currency:toman'],
        ['text' => 'USD', 'callback_data' => 'action:pick_currency;currency:usd'],
        ['text' => 'EUR', 'callback_data' => 'action:pick_currency;currency:eur'],
    ]);
});
