<?php

use App\Enums\AssetType;
use App\Enums\Feature;
use App\Models\InvestmentAsset;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Support\Carbon;

function goalPayload(array $overrides = []): array
{
    return [
        'investment_asset_id' => InvestmentAsset::query()
            ->where('slug', AssetType::Gold->value)
            ->firstOrFail()
            ->id,
        'title' => 'Nowruz fund',
        'target_quantity' => 3,
        'target_date' => Carbon::today()->addDays(180)->toDateString(),
        ...$overrides,
    ];
}

test('a user can set a goal denominated in an asset unit', function () {
    $user = User::factory()->withModules(Feature::Portfolio)->create();

    $this->actingAs($user)
        ->post(route('savings-goals.store'), goalPayload())
        ->assertRedirect();

    $goal = $user->savingsGoals()->firstOrFail();

    expect((float) $goal->target_quantity)->toBe(3.0)
        ->and($goal->title)->toBe('Nowruz fund')
        // Defaults to today, so holdings the user already had are a baseline
        // rather than progress they just made.
        ->and($goal->started_on->toDateString())->toBe(Carbon::today()->toDateString());
});

test('a target date in the past is refused', function () {
    $user = User::factory()->withModules(Feature::Portfolio)->create();

    $this->actingAs($user)
        ->post(route('savings-goals.store'), goalPayload([
            'target_date' => Carbon::today()->subDay()->toDateString(),
        ]))
        ->assertSessionHasErrors('target_date');
});

test('a goal cannot be set on an asset the user cannot use', function () {
    $user = User::factory()->withModules(Feature::Portfolio)->create();
    $stranger = User::factory()->create();

    $private = InvestmentAsset::query()->create([
        'user_id' => $stranger->id,
        'name' => 'Someone elses asset',
        'unit' => 'unit',
    ]);

    $this->actingAs($user)
        ->post(route('savings-goals.store'), goalPayload([
            'investment_asset_id' => $private->id,
        ]))
        ->assertSessionHasErrors('investment_asset_id');
});

test('a user cannot touch another user goal', function () {
    $user = User::factory()->withModules(Feature::Portfolio)->create();
    $stranger = User::factory()->withModules(Feature::Portfolio)->create();

    $goal = SavingsGoal::factory()->for($stranger)->create();

    // 404 rather than 403 — a goal belonging to someone else should not be
    // confirmed to exist.
    $this->actingAs($user)
        ->patch(route('savings-goals.update', $goal), goalPayload())
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('savings-goals.destroy', $goal))
        ->assertNotFound();

    expect(SavingsGoal::query()->whereKey($goal->id)->exists())->toBeTrue();
});

test('goals are gated on the portfolio module', function () {
    // No withModules() — portfolio ships switched off.
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('savings-goals.store'), goalPayload())
        ->assertRedirect(route('modules.edit'));

    expect($user->savingsGoals()->count())->toBe(0);
});

test('a goal survives a round trip through update', function () {
    $user = User::factory()->withModules(Feature::Portfolio)->create();
    $goal = SavingsGoal::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('savings-goals.update', $goal), goalPayload([
            'target_quantity' => 5.5,
            'title' => 'Bigger fund',
        ]))
        ->assertRedirect();

    expect((float) $goal->fresh()->target_quantity)->toBe(5.5)
        ->and($goal->fresh()->title)->toBe('Bigger fund');
});

test('editing a goal leaves its pace baseline where it was', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00'));

    try {
        $user = User::factory()->withModules(Feature::Portfolio)->create();
        $goal = SavingsGoal::factory()->for($user)->create([
            'started_on' => '2026-06-01',
            'target_date' => '2026-12-01',
        ]);

        // Two months later the user renames the goal and changes nothing else.
        Carbon::setTestNow(Carbon::parse('2026-08-01 09:00:00'));

        $this->actingAs($user)
            ->patch(route('savings-goals.update', $goal), goalPayload([
                'title' => 'Renamed',
                'target_date' => '2026-12-01',
            ]))
            ->assertRedirect();

        // Defaulting started_on on update would discard two months of baseline
        // and rewrite every pace figure the goal has ever shown.
        expect($goal->fresh()->started_on->toDateString())->toBe('2026-06-01');
    } finally {
        Carbon::setTestNow();
    }
});

test('an explicit started_on is still honoured on update', function () {
    $user = User::factory()->withModules(Feature::Portfolio)->create();
    $goal = SavingsGoal::factory()->for($user)->create(['started_on' => '2026-06-01']);

    $this->actingAs($user)
        ->patch(route('savings-goals.update', $goal), goalPayload([
            'started_on' => '2026-07-15',
        ]))
        ->assertRedirect();

    expect($goal->fresh()->started_on->toDateString())->toBe('2026-07-15');
});
