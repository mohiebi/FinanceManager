<?php

use App\Telegraph\TelegramHandler;

test('telegram main keyboard groups related actions into rows', function () {
    $handler = new TelegramHandler;
    $method = new \ReflectionMethod($handler, 'mainKeyboard');
    $method->setAccessible(true);

    $keyboard = $method->invoke($handler)->toArray();

    expect(collect($keyboard)->map(fn (array $row): array => array_column($row, 'text'))->all())
        ->toBe([
            ['Add cost', 'Add income'],
            ['Last transactions', 'Add investment'],
            ['Today report', 'Week report', 'Month report'],
        ]);
});
