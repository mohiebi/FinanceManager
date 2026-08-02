<?php

use App\Actions\Features\UpdateUserFeature;
use App\Enums\Feature;
use App\Models\User;
use App\Telegraph\TelegramHandler;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Support\Facades\Http;

function keyboardHandlerFor(User $user): TelegramHandler
{
    $chat = TelegraphChat::factory()->create(['chat_id' => $user->telegram_chat_id]);

    $handler = new TelegramHandler;
    $property = new ReflectionProperty($handler, 'chat');
    $property->setAccessible(true);
    $property->setValue($handler, $chat);

    return $handler;
}

/**
 * @return array<int, array<int, string>>
 */
function mainKeyboardRowsFor(User $user): array
{
    $method = new ReflectionMethod(TelegramHandler::class, 'mainKeyboard');
    $method->setAccessible(true);

    return collect($method->invoke(keyboardHandlerFor($user))->toArray())
        ->map(fn (array $row): array => array_column($row, 'text'))
        ->filter(fn (array $row): bool => $row !== [])
        ->values()
        ->all();
}

function enableTelegramBotFor(User $user): void
{
    app(UpdateUserFeature::class)($user, Feature::TelegramBot, true);
}

test('telegram main keyboard groups related actions into rows', function () {
    $user = User::factory()->withModules()->create(['telegram_chat_id' => '555222']);

    expect(mainKeyboardRowsFor($user))->toBe([
        ['Add cost', 'Add income'],
        ['Last 10 transactions', 'Add investment'],
        ['Add bill', 'My bills'],
        ['Portfolio', 'Flight plan'],
        ['Nothing spent today', 'Daily report'],
        ['Weekly report', 'Monthly report'],
    ]);
});

test('telegram main keyboard omits buttons for modules that are switched off', function () {
    // The flight log ships on, so it has to be switched off explicitly for this
    // to be a test about switched-off modules.
    $user = User::factory()->withoutModules(Feature::Gamification)
        ->create(['telegram_chat_id' => '555333']);
    enableTelegramBotFor($user);

    expect(mainKeyboardRowsFor($user))->toBe([
        ['Add cost', 'Add income'],
        ['Last 10 transactions', 'Daily report'],
        ['Weekly report', 'Monthly report'],
    ]);
});

test('telegram main keyboard is empty when telegram bot is switched off', function () {
    $user = User::factory()->create(['telegram_chat_id' => '555335']);

    expect(mainKeyboardRowsFor($user))->toBe([]);
});

test('a stale telegram callback for a disabled module replies instead of acting', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

    $user = User::factory()->create(['telegram_chat_id' => '555444']);
    enableTelegramBotFor($user);

    keyboardHandlerFor($user)->add_bill();

    Http::assertSent(fn ($request) => str_contains($request['text'] ?? '', 'Bills'));
});

test('a stale telegram action is refused when telegram bot is switched off', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

    $user = User::factory()->create(['telegram_chat_id' => '555445']);

    keyboardHandlerFor($user)->add_cost();

    Http::assertSent(fn ($request) => str_contains($request['text'] ?? '', 'Telegram Bot'));
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

test('the no-spend button appears only with the gamification module on', function () {
    $user = User::factory()->withoutModules(Feature::Gamification)
        ->create(['telegram_chat_id' => '12345']);
    enableTelegramBotFor($user);

    $labels = collect(mainKeyboardRowsFor($user->fresh()))->flatten()->all();
    expect($labels)->not->toContain('Nothing spent today');

    app(UpdateUserFeature::class)($user, Feature::Gamification, true);

    // Old keyboards persist in chat history, so the handler re-checks the module
    // on every callback too — this only covers what a fresh menu offers.
    $labels = collect(mainKeyboardRowsFor($user->fresh()))->flatten()->all();
    expect($labels)->toContain('Nothing spent today');
});
