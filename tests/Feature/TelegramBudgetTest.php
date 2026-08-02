<?php

use App\Enums\Feature;
use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\User;
use App\Telegraph\TelegramHandler;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Support\Facades\Http;

/**
 * The bot's view of a flight plan.
 *
 * No vault cases here on purpose: Feature::Vault conflicts with
 * Feature::TelegramBot, so a user whose amounts the server cannot read has no
 * linked chat for any of this to reach.
 */
function budgetHandlerFor(User $user): TelegramHandler
{
    $chat = TelegraphChat::factory()->create(['chat_id' => $user->telegram_chat_id]);

    $handler = new TelegramHandler;
    $property = new ReflectionProperty($handler, 'chat');
    $property->setAccessible(true);
    $property->setValue($handler, $chat);

    return $handler;
}

/** The text of the last message the bot sent. */
function lastBotMessage(): string
{
    $sent = collect(Http::recorded())
        ->map(fn (array $pair): array => $pair[0]->data())
        ->filter(fn (array $data): bool => isset($data['text']))
        ->last();

    return $sent['text'] ?? '';
}

beforeEach(function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
});

test('the bot reports what each line may still spend', function () {
    $user = User::factory()
        ->withModules(Feature::TelegramBot, Feature::Budgets)
        ->create(['telegram_chat_id' => '777001', 'default_currency' => 'toman']);

    $investing = Category::query()->firstOrCreate(
        ['type' => TransactionType::Cost, 'slug' => 'investing'],
        ['name' => 'Investing', 'user_id' => null],
    );
    $salary = Category::query()->firstOrCreate(
        ['type' => TransactionType::Income, 'slug' => 'salary'],
        ['name' => 'Salary', 'user_id' => null],
    );

    $budget = Budget::factory()->create(['user_id' => $user->id]);
    BudgetLine::factory()->percent(50)->create([
        'budget_id' => $budget->id,
        'category_id' => $investing->id,
    ]);

    foreach ([[$salary, 24000000, TransactionType::Income], [$investing, 4200000, TransactionType::Cost]] as [$category, $amount, $type]) {
        $user->transactions()->create([
            'category_id' => $category->id,
            'type' => $type,
            'amount' => $amount,
            'currency' => 'toman',
            'title' => 'Entry',
            'occurred_at' => now()->toDateString(),
        ]);
    }

    budgetHandlerFor($user)->budget();

    $message = lastBotMessage();

    // 50% of 24,000,000 is 12,000,000; 4,200,000 of it is spent, so 7,800,000
    // is the number the user opened Telegram to find out.
    expect($message)->toContain('Investing')
        ->and($message)->toContain('12,000,000')
        // The suffix comes from fmtAmount, the same formatter the wizards use.
        ->and($message)->toContain('7,800,000 T left');
});

test('the bot flags a line that has gone over', function () {
    $user = User::factory()
        ->withModules(Feature::TelegramBot, Feature::Budgets)
        ->create(['telegram_chat_id' => '777002']);

    $food = Category::query()->firstOrCreate(
        ['type' => TransactionType::Cost, 'slug' => 'food'],
        ['name' => 'Food', 'user_id' => null],
    );

    $budget = Budget::factory()->create(['user_id' => $user->id]);
    BudgetLine::factory()->fixed(1000000)->create([
        'budget_id' => $budget->id,
        'category_id' => $food->id,
    ]);

    $user->transactions()->create([
        'category_id' => $food->id,
        'type' => TransactionType::Cost,
        'amount' => 1500000,
        'currency' => 'toman',
        'title' => 'Groceries',
        'occurred_at' => now()->toDateString(),
    ]);

    budgetHandlerFor($user)->budget();

    expect(lastBotMessage())->toContain('500,000 T over');
});

test('a user with no plan is told where to make one rather than shown an empty report', function () {
    $user = User::factory()
        ->withModules(Feature::TelegramBot, Feature::Budgets)
        ->create(['telegram_chat_id' => '777003']);

    budgetHandlerFor($user)->budget();

    expect(lastBotMessage())->toContain('No flight plan yet');
});

test('the command is gated on the budgets module', function () {
    $user = User::factory()
        ->withModules(Feature::TelegramBot)
        ->withoutModules(Feature::Budgets)
        ->create(['telegram_chat_id' => '777004']);

    $budget = Budget::factory()->create(['user_id' => $user->id]);
    BudgetLine::factory()->remainder()->create(['budget_id' => $budget->id]);

    budgetHandlerFor($user)->budget();

    expect(lastBotMessage())->not->toContain('Flight plan —');
});
