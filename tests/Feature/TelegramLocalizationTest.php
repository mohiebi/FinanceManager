<?php

use App\Enums\Currency;
use App\Enums\Feature;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TelegramReportService;
use App\Telegraph\TelegramHandler;
use Carbon\Carbon;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Support\Facades\Http;

/**
 * The bot speaks the language the account chose in Settings > Preferences.
 *
 * A webhook carries no session, so nothing sets a locale for the request — the
 * application is still on its default when the handler starts. These tests
 * deliberately leave it there and assert on the reply, which is the only place
 * the user's own preference can show up.
 */
function localizedHandlerFor(User $user): TelegramHandler
{
    $chat = TelegraphChat::factory()->create(['chat_id' => $user->telegram_chat_id]);

    $handler = new TelegramHandler;
    $property = new ReflectionProperty($handler, 'chat');
    $property->setAccessible(true);
    $property->setValue($handler, $chat);

    return $handler;
}

/** Every payload the bot sent, message text and keyboard alike. */
function lastBotPayload(): array
{
    return collect(Http::recorded())
        ->map(fn (array $pair): array => $pair[0]->data())
        ->filter(fn (array $data): bool => isset($data['text']))
        ->last() ?? [];
}

/** The button labels of the keyboard attached to the last message. */
function lastBotKeyboard(): string
{
    return json_encode(lastBotPayload()['reply_markup'] ?? [], JSON_UNESCAPED_UNICODE) ?: '';
}

beforeEach(function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
});

test('the bot greets a Persian user in Persian', function () {
    $user = User::factory()->withModules()->create([
        'telegram_chat_id' => '910001',
        'locale' => 'fa',
        'name' => 'مریم',
    ]);

    localizedHandlerFor($user)->help();

    $payload = lastBotPayload();

    expect($payload['text'])->toContain('یک کار را انتخاب کنید')
        ->and($payload['text'])->toContain('مریم')
        ->and(lastBotKeyboard())->toContain('ثبت هزینه')
        ->and(lastBotKeyboard())->toContain('گزارش ماهانه')
        ->and(lastBotKeyboard())->not->toContain('Add cost');

    // The preference is the user's, not the application's: nothing in this
    // request ever set a locale, so a leaked app default would show up here.
    expect(app()->getLocale())->toBe('fa');
});

test('the bot greets a German user in German', function () {
    $user = User::factory()->withModules()->create([
        'telegram_chat_id' => '910002',
        'locale' => 'de',
    ]);

    localizedHandlerFor($user)->help();

    $payload = lastBotPayload();

    expect($payload['text'])->toContain('Wähle eine Aktion')
        ->and(lastBotKeyboard())->toContain('Ausgabe erfassen')
        ->and(lastBotKeyboard())->not->toContain('Add cost');
});

test('an English account is unaffected by the translated bot', function () {
    $user = User::factory()->withModules()->create(['telegram_chat_id' => '910003']);

    localizedHandlerFor($user)->help();

    $payload = lastBotPayload();

    expect($payload['text'])->toContain('Choose an action')
        ->and(lastBotKeyboard())->toContain('Add cost');
});

test('a chat with no linked account still gets an answer in the app default', function () {
    $chat = TelegraphChat::factory()->create(['chat_id' => '910004']);
    $handler = new TelegramHandler;
    $property = new ReflectionProperty($handler, 'chat');
    $property->setAccessible(true);
    $property->setValue($handler, $chat);

    $handler->help();

    expect(lastBotPayload()['text'])->toContain('not linked to an account');
});

test('a locked module is explained in the account language', function () {
    $user = User::factory()
        ->withModules(Feature::TelegramBot)
        ->withoutModules(Feature::Bills)
        ->create(['telegram_chat_id' => '910005', 'locale' => 'fa']);

    localizedHandlerFor($user)->list_bills();

    expect(lastBotPayload()['text'])->toContain('خاموش است');
});

test('a Persian wizard prompt and its cancel button are both Persian', function () {
    $user = User::factory()->withModules()->create([
        'telegram_chat_id' => '910006',
        'locale' => 'fa',
    ]);

    localizedHandlerFor($user)->add_cost();

    $payload = lastBotPayload();

    expect($payload['text'])->toContain('ثبت هزینه')
        ->and(lastBotKeyboard())->toContain('انصراف و بازگشت به منو');
});

test('a report is written in the recipient language', function () {
    $user = User::factory()->withModules()->create(['locale' => 'fa']);
    $category = Category::factory()->cost()->forUser($user)->create(['name' => 'خوراک']);

    Transaction::factory()->cost()->for($user)->for($category)->create([
        'amount' => 1000000,
        'currency' => Currency::Toman,
        'occurred_at' => '2026-06-04',
    ]);

    $report = app(TelegramReportService::class)->weekly($user, Carbon::parse('2026-06-06'));

    expect($report)->toContain('گزارش هفتگی')
        ->toContain('هزینه‌ها: *1,000,000 تومان*')
        ->toContain('*تفکیک هزینه‌ها:*')
        ->not->toContain('Weekly Report');
});

test('an empty report says so in the recipient language', function () {
    $user = User::factory()->withModules()->create(['locale' => 'de']);

    $report = app(TelegramReportService::class)->daily($user, Carbon::parse('2026-06-06'));

    expect($report)->toContain('Tagesbericht')
        ->toContain('Keine Aktivität in diesem Zeitraum');
});

test('every bot string exists in all three locales', function () {
    $flatten = function (array $messages, string $prefix = '') use (&$flatten): array {
        $keys = [];

        foreach ($messages as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            $keys = [...$keys, ...(is_array($value) ? $flatten($value, $path) : [$path])];
        }

        return $keys;
    };

    $english = $flatten(require resource_path('lang/en/telegram.php'));
    $missing = [];

    foreach (['fa', 'de'] as $locale) {
        $translated = $flatten(require resource_path("lang/{$locale}/telegram.php"));

        foreach (array_diff($english, $translated) as $key) {
            $missing[] = "{$locale}.{$key}";
        }
    }

    expect($missing)->toBe([]);
});
