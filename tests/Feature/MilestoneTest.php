<?php

use App\Enums\Feature;
use App\Enums\Milestone;
use App\Enums\PilotRank;
use App\Jobs\SendTelegramMessageJob;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\MilestoneNotification;
use App\Support\LogbookCompleteness;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

test('the first record earns a milestone, and the second does not repeat it', function () {
    Notification::fake();

    $user = User::factory()->withModules(Feature::Gamification)->create();
    $category = Category::factory()->cost()->forUser($user)->create();

    Transaction::factory()->cost()->for($user)->for($category)->create();
    Transaction::factory()->cost()->for($user)->for($category)->create();

    // The unique key on (user_id, key) is what makes this once-only — a
    // milestone that repeats is just a notification.
    expect($user->milestones()->where('key', Milestone::FirstTransaction->value)->count())
        ->toBe(1);

    Notification::assertSentToTimes($user, MilestoneNotification::class, 1);
});

test('the observer fires on every path that writes a transaction', function () {
    $user = User::factory()->withModules(Feature::Gamification)->create();

    // Not through the factory or a controller: the Telegram wizard writes with a
    // bare Transaction::create, and a milestone that only fires on the web form
    // would be worse than none.
    Transaction::create([
        'user_id' => $user->id,
        'type' => 'cost',
        'amount' => 1000,
        'currency' => 'toman',
        'title' => 'Taxi',
        'occurred_at' => now()->toDateString(),
    ]);

    expect($user->milestones()->count())->toBe(1);
});

test('milestones are not awarded while the module is off', function () {
    // The flight log ships ON, unlike every other optional module, so this has
    // to switch it off rather than simply not switching it on.
    $user = User::factory()->withoutModules(Feature::Gamification)->create();
    $category = Category::factory()->cost()->forUser($user)->create();

    Transaction::factory()->cost()->for($user)->for($category)->create();

    expect($user->milestones()->count())->toBe(0);
});

test('the hundredth record is recognised', function () {
    Notification::fake();

    $user = User::factory()->withModules(Feature::Gamification)->create();
    $category = Category::factory()->cost()->forUser($user)->create();

    Transaction::factory()->cost()->for($user)->for($category)->count(100)->create();

    expect($user->milestones()->pluck('key')->map->value->all())
        ->toContain(Milestone::FirstTransaction->value)
        ->toContain(Milestone::HundredTransactions->value);
});

test('a milestone payload carries no amounts', function () {
    $user = User::factory()->withModules(Feature::Gamification)->create();
    $category = Category::factory()->cost()->forUser($user)->create();

    Transaction::factory()->cost()->for($user)->for($category)->create([
        'amount' => 987654,
        'title' => 'Very secret purchase',
    ]);

    // notifications.data is not encrypted, so nothing the transactions table
    // bothers to encrypt may be repeated in it.
    $stored = json_encode($user->notifications()->first()->data);

    expect($stored)->not->toContain('987654')
        ->and($stored)->not->toContain('Very secret purchase');
});

test('rank is earned by days recorded, never by balance', function () {
    $cadet = User::factory()->withModules(Feature::Gamification)->create();
    $captain = User::factory()->withModules(Feature::Gamification)->create();

    // A single enormous transaction does not outrank 200 small ones.
    $category = Category::factory()->cost()->forUser($cadet)->create();
    Transaction::factory()->cost()->for($cadet)->for($category)->create([
        'amount' => 99_000_000_000,
    ]);

    foreach (range(1, 200) as $day) {
        $captain->noSpendDays()->create([
            'date' => now()->subDays($day)->toDateString(),
        ]);
    }

    expect(PilotRank::forDaysLogged(1))->toBe(PilotRank::Cadet)
        ->and(PilotRank::forDaysLogged(200))->toBe(PilotRank::Captain)
        ->and(app(LogbookCompleteness::class)->for($cadet)['rank'])
        ->toBe(PilotRank::Cadet->value)
        ->and(app(LogbookCompleteness::class)->for($captain)['rank'])
        ->toBe(PilotRank::Captain->value);
});

test('a bulk import does not re-count the table for every row', function () {
    $user = User::factory()->withModules(Feature::Gamification)->create();
    $category = Category::factory()->cost()->forUser($user)->create();

    // First row settles the cheap milestones and warms the action's memo.
    Transaction::factory()->cost()->for($user)->for($category)->create();

    DB::enableQueryLog();
    DB::flushQueryLog();

    foreach (range(1, 10) as $ignored) {
        $user->transactions()->create([
            'type' => 'cost',
            'amount' => 100,
            'currency' => 'toman',
            'title' => 'Imported row',
            'category_id' => $category->id,
            'occurred_at' => now()->toDateString(),
        ]);
    }

    $queries = array_column(DB::getQueryLog(), 'query');
    DB::disableQueryLog();

    // The importer writes one row at a time, so anything the observer does here
    // is multiplied by the size of the file.
    expect(array_filter($queries, fn (string $q): bool => str_contains($q, 'count(*)')))
        ->toBeEmpty()
        ->and(array_filter($queries, fn (string $q): bool => str_contains($q, 'from "users"')))
        ->toBeEmpty();
});

test('milestones stay in the bell rather than pushing telegram messages', function () {
    Queue::fake();

    $user = User::factory()->withModules(Feature::Gamification)->create([
        'telegram_chat_id' => '4242',
        'streak_nudge_enabled' => false,
    ]);

    $user->transactions()->create([
        'type' => 'cost',
        'amount' => 100,
        'currency' => 'toman',
        'title' => 'Row',
        'occurred_at' => now()->toDateString(),
    ]);

    // The only Telegram switch a user has is the streak reminder; a milestone
    // riding on it would be a message they never asked for.
    Queue::assertNotPushed(SendTelegramMessageJob::class);
    expect($user->notifications()->count())->toBe(1);
});

test('a full previous month earns the milestone on any visit in the next one', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-05 09:00:00'));

    try {
        $user = User::factory()->withModules(Feature::Gamification)->create([
            'created_at' => Carbon::parse('2026-06-01 00:00:00'),
        ]);

        foreach (range(1, 31) as $day) {
            $user->noSpendDays()->create(['date' => sprintf('2026-07-%02d', $day)]);
        }

        // Not the 31st of July — the whole point is that it no longer takes a
        // visit on one specific day.
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        expect($user->milestones()->pluck('key')->map->value->all())
            ->toContain(Milestone::FirstFullMonth->value);
    } finally {
        Carbon::setTestNow();
    }
});

test('a previous month with a gap earns nothing', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-05 09:00:00'));

    try {
        $user = User::factory()->withModules(Feature::Gamification)->create([
            'created_at' => Carbon::parse('2026-06-01 00:00:00'),
        ]);

        foreach (range(1, 30) as $day) {
            $user->noSpendDays()->create(['date' => sprintf('2026-07-%02d', $day)]);
        }

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        expect($user->milestones()->pluck('key')->map->value->all())
            ->not->toContain(Milestone::FirstFullMonth->value);
    } finally {
        Carbon::setTestNow();
    }
});

test('rank counts a day holding both a transaction and a no-spend marker once', function () {
    $user = User::factory()->withModules(Feature::Gamification)->create();
    $category = Category::factory()->cost()->forUser($user)->create();

    $user->noSpendDays()->create(['date' => '2026-07-10']);
    // Backdating an import row onto a day already marked spend-free is the way
    // this overlap actually happens.
    Transaction::factory()->cost()->for($user)->for($category)->create([
        'occurred_at' => '2026-07-10',
    ]);

    expect(app(LogbookCompleteness::class)->for($user, CarbonImmutable::parse('2026-07-20'))['days_logged'])
        ->toBe(1);
});
