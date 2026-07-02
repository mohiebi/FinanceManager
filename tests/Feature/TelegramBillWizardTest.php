<?php

use App\Models\Bill;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Telegraph\TelegramHandler;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Stringable;

function telegramHandlerFor(User $user): TelegramHandler
{
    $chat = TelegraphChat::factory()->create(['chat_id' => $user->telegram_chat_id]);

    $handler = new TelegramHandler;
    $property = new ReflectionProperty($handler, 'chat');
    $property->setAccessible(true);
    $property->setValue($handler, $chat);

    return $handler;
}

function sendBillWizardText(TelegramHandler $handler, string $text): void
{
    $method = new ReflectionMethod($handler, 'handleChatMessage');
    $method->setAccessible(true);
    $method->invoke($handler, new Stringable($text));
}

beforeEach(function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
});

test('the full add-bill wizard creates a monthly bill with its first occurrence', function () {
    $user = User::factory()->create(['telegram_chat_id' => '555111']);
    Category::factory()->cost()->create(['name' => 'Housing']);

    $handler = telegramHandlerFor($user);

    $handler->add_bill();
    sendBillWizardText($handler, 'Rent');
    sendBillWizardText($handler, '5000000');
    $handler->bill_pick_currency('toman');
    $handler->bill_pick_category('0');
    $handler->bill_pick_recurrence('monthly');
    sendBillWizardText($handler, '15');
    $handler->confirm_bill();

    $bill = Bill::query()->sole();

    expect($bill->title)->toBe('Rent')
        ->and((float) $bill->amount)->toBe(5000000.0)
        ->and($bill->currency)->toBe('toman')
        ->and($bill->recurrence_type->value)->toBe('monthly')
        ->and($bill->due_day_of_month)->toBe(15)
        ->and($bill->category_id)->toBeNull();

    expect($bill->occurrences()->count())->toBe(1);
});

test('the add-bill wizard supports a one-time bill with a category', function () {
    $user = User::factory()->create(['telegram_chat_id' => '555222']);
    $category = Category::factory()->cost()->create(['name' => 'Housing']);

    $handler = telegramHandlerFor($user);

    $handler->add_bill();
    sendBillWizardText($handler, 'Annual fee');
    sendBillWizardText($handler, '1200000');
    $handler->bill_pick_currency('toman');
    $handler->bill_pick_category((string) $category->id);
    $handler->bill_pick_recurrence('one_time');
    sendBillWizardText($handler, '2026-09-01');
    $handler->confirm_bill();

    $bill = Bill::query()->sole();

    expect($bill->recurrence_type->value)->toBe('one_time')
        ->and($bill->due_date->toDateString())->toBe('2026-09-01')
        ->and($bill->category_id)->toBe($category->id);

    $occurrence = $bill->occurrences()->sole();
    expect($occurrence->due_date->toDateString())->toBe('2026-09-01');
});

test('cancelling the wizard does not create a bill', function () {
    $user = User::factory()->create(['telegram_chat_id' => '555333']);
    $handler = telegramHandlerFor($user);

    $handler->add_bill();
    sendBillWizardText($handler, 'Rent');
    $handler->cancel_bill();

    expect(Bill::query()->count())->toBe(0);
});

test('cancel current clears running telegram wizards and returns to menu', function () {
    $user = User::factory()->create(['telegram_chat_id' => '555334']);
    $handler = telegramHandlerFor($user);

    $handler->add_bill();
    sendBillWizardText($handler, 'Rent');

    $handler->cancel_current();

    $chatProperty = new ReflectionProperty($handler, 'chat');
    $chatProperty->setAccessible(true);
    $chat = $chatProperty->getValue($handler);

    expect($chat->storage()->get('wizard'))->toBe([])
        ->and($chat->storage()->get('inv_wizard'))->toBe([])
        ->and($chat->storage()->get('bill_wizard'))->toBe([])
        ->and(Bill::query()->count())->toBe(0);
});

test('rejects a non-numeric amount and stays on the same step', function () {
    $user = User::factory()->create(['telegram_chat_id' => '555444']);
    $handler = telegramHandlerFor($user);

    $handler->add_bill();
    sendBillWizardText($handler, 'Rent');
    sendBillWizardText($handler, 'not a number');

    $handler->bill_pick_currency('toman');

    // The wizard never advanced past "amount", so currency selection bails out.
    expect(Bill::query()->count())->toBe(0);
});

test('pay_bill marks the occurrence paid and creates a matching cost transaction', function () {
    $user = User::factory()->create(['telegram_chat_id' => '555555']);
    $category = Category::factory()->cost()->create();

    $bill = $user->bills()->create([
        'title' => 'Phone',
        'amount' => 250000,
        'currency' => 'toman',
        'category_id' => $category->id,
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 15,
    ]);
    $occurrence = $bill->occurrences()->create(['due_date' => '2026-07-15']);

    $handler = telegramHandlerFor($user);
    $handler->pay_bill((string) $bill->id, (string) $occurrence->id);

    $occurrence->refresh();
    expect($occurrence->isPaid())->toBeTrue();

    $transaction = Transaction::query()->sole();
    expect((float) $transaction->amount)->toBe(250000.0)
        ->and($transaction->category_id)->toBe($category->id)
        ->and($transaction->title)->toBe('Phone');
});

test('list_bills sends a message for bills with and without a pending occurrence', function () {
    $user = User::factory()->create([
        'telegram_chat_id' => '555888',
        'calendar' => 'jalali',
    ]);

    $withOccurrence = $user->bills()->create([
        'title' => 'Rent',
        'amount' => 100,
        'currency' => 'toman',
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 1,
    ]);
    $withOccurrence->occurrences()->create(['due_date' => '2026-08-01']);

    $user->bills()->create([
        'title' => 'Fully paid bill',
        'amount' => 50,
        'currency' => 'toman',
        'recurrence_type' => 'one_time',
        'due_date' => '2026-01-01',
    ]);

    $handler = telegramHandlerFor($user);
    $handler->list_bills();

    Http::assertSent(function ($request): bool {
        $body = (string) $request->body();

        return str_contains($request->url(), 'api.telegram.org')
            && str_contains($body, 'Mark paid: Rent')
            && str_contains($body, 'due 1405-05-10')
            && ! str_contains($body, 'due 2026-08-01')
            && str_contains($body, 'Back to menu')
            && str_contains($body, 'action:help');
    });
});

test('pay_bill does not let a user pay another users bill', function () {
    $owner = User::factory()->create(['telegram_chat_id' => '555666']);
    $intruder = User::factory()->create(['telegram_chat_id' => '555777']);

    $bill = $owner->bills()->create([
        'title' => 'Rent',
        'amount' => 100,
        'currency' => 'toman',
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 1,
    ]);
    $occurrence = $bill->occurrences()->create(['due_date' => '2026-07-01']);

    $handler = telegramHandlerFor($intruder);
    $handler->pay_bill((string) $bill->id, (string) $occurrence->id);

    expect($occurrence->fresh()->isPaid())->toBeFalse()
        ->and(Transaction::query()->count())->toBe(0);
});
