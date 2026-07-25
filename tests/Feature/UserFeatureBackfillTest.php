<?php

use App\Enums\Feature;
use App\Models\InvestmentAsset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Token;

function runFeatureBackfill(): void
{
    $migration = require database_path('migrations/2026_07_25_102921_backfill_user_features.php');

    $migration->up();
}

function createBillFor(User $user): void
{
    $user->bills()->create([
        'title' => 'Rent',
        'amount' => 5000000,
        'currency' => 'toman',
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 1,
    ]);
}

function createInvestmentFor(User $user): void
{
    $asset = InvestmentAsset::query()->whereNull('user_id')->firstOrFail();

    $user->investments()->create([
        'investment_asset_id' => $asset->id,
        'asset_type' => $asset->slug,
        'quantity' => 2,
        'occurred_at' => now()->toDateString(),
    ]);
}

function createBackfillOauthClient(): Client
{
    return Client::query()->forceCreate([
        'name' => 'Claude',
        'secret' => null,
        'provider' => null,
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
        'grant_types' => ['authorization_code', 'refresh_token'],
        'revoked' => false,
    ]);
}

function createBackfillMcpToken(User $user, Client $client): Token
{
    return Token::query()->forceCreate([
        'id' => Str::random(80),
        'user_id' => $user->id,
        'client_id' => $client->getKey(),
        'name' => null,
        'scopes' => ['mcp:use'],
        'revoked' => false,
        'created_at' => now(),
        'updated_at' => now(),
        'expires_at' => now()->addDays(15),
    ]);
}

test('the backfill switches on modules the user already had data in', function () {
    $billsUser = User::factory()->create();
    $investmentsUser = User::factory()->create();
    $telegramUser = User::factory()->create(['telegram_chat_id' => '123456']);
    $aiUser = User::factory()->create();
    $newUser = User::factory()->create();
    $client = createBackfillOauthClient();

    createBillFor($billsUser);
    createInvestmentFor($investmentsUser);
    createBackfillMcpToken($aiUser, $client);

    runFeatureBackfill();

    expect($billsUser->fresh()->hasFeature(Feature::Bills))->toBeTrue()
        ->and($billsUser->fresh()->hasFeature(Feature::Investments))->toBeFalse();

    // Portfolio is a view over investments, so it rides along.
    expect($investmentsUser->fresh()->hasFeature(Feature::Investments))->toBeTrue()
        ->and($investmentsUser->fresh()->hasFeature(Feature::Portfolio))->toBeTrue()
        ->and($investmentsUser->fresh()->hasFeature(Feature::Bills))->toBeFalse();

    // Nobody without data gets a row — they stay on the defaults and see the promo.
    expect($telegramUser->fresh()->hasFeature(Feature::TelegramBot))->toBeTrue()
        ->and($telegramUser->fresh()->hasFeature(Feature::AiAssistant))->toBeFalse();

    expect($aiUser->fresh()->hasFeature(Feature::AiAssistant))->toBeTrue()
        ->and($aiUser->fresh()->hasFeature(Feature::TelegramBot))->toBeFalse();

    expect($newUser->fresh()->features()->count())->toBe(0)
        ->and($newUser->fresh()->hasFeature(Feature::Reports))->toBeTrue();
});

test('a user with a custom investment asset but no holdings still counts', function () {
    $user = User::factory()->create();

    InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Emerald',
        'slug' => 'emerald-'.$user->id,
        'unit' => 'ct',
    ]);

    runFeatureBackfill();

    expect($user->fresh()->hasFeature(Feature::Investments))->toBeTrue();
});

test('running the backfill twice changes nothing', function () {
    $user = User::factory()->create();
    createBillFor($user);

    runFeatureBackfill();
    $afterFirstRun = DB::table('user_features')->count();

    runFeatureBackfill();

    expect(DB::table('user_features')->count())->toBe($afterFirstRun)
        ->and($afterFirstRun)->toBe(1);
});

test('the backfill does not overwrite a choice the user already made', function () {
    $user = User::factory()->create();
    createBillFor($user);

    // The user explicitly switched bills off before the backfill ran.
    $user->features()->create(['feature' => Feature::Bills->value, 'enabled' => false]);

    runFeatureBackfill();

    expect($user->fresh()->hasFeature(Feature::Bills))->toBeFalse();
});
