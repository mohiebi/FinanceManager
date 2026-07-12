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
            ['Last 10 transactions', 'Add investment'],
            ['Add bill', 'My bills'],
            ['Portfolio', 'Daily report'],
            ['Weekly report', 'Monthly report'],
        ]);
});

test('telegram bill wizard offers currency choices', function () {
    $handler = new TelegramHandler;
    $method = new ReflectionMethod($handler, 'billCurrencyKeyboard');
    $method->setAccessible(true);

    $keyboard = $method->invoke($handler)->toArray();

    expect(collect($keyboard)->flatten(1)->map(fn (array $button): array => [
        'text' => $button['text'],
        'callback_data' => $button['callback_data'],
    ])->all())->toBe([
        ['text' => 'TOMAN', 'callback_data' => 'action:bill_pick_currency;currency:toman'],
        ['text' => 'USD', 'callback_data' => 'action:bill_pick_currency;currency:usd'],
        ['text' => 'EUR', 'callback_data' => 'action:bill_pick_currency;currency:eur'],
        ['text' => 'Cancel and menu', 'callback_data' => 'action:cancel_current'],
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
        ['text' => 'Cancel and menu', 'callback_data' => 'action:cancel_current'],
    ]);
});

test('telegram bill recurrence wizard can be cancelled back to menu', function () {
    $handler = new TelegramHandler;
    $method = new ReflectionMethod($handler, 'billRecurrenceKeyboard');
    $method->setAccessible(true);

    $keyboard = $method->invoke($handler)->toArray();

    expect(collect($keyboard)->flatten(1)->map(fn (array $button): array => [
        'text' => $button['text'],
        'callback_data' => $button['callback_data'],
    ])->all())->toBe([
        ['text' => 'Monthly', 'callback_data' => 'action:bill_pick_recurrence;type:monthly'],
        ['text' => 'One-time', 'callback_data' => 'action:bill_pick_recurrence;type:one_time'],
        ['text' => 'Cancel and menu', 'callback_data' => 'action:cancel_current'],
    ]);
});
