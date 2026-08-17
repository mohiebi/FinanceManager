<?php

use App\Models\AdvisorRecommendation;
use App\Models\Bill;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\InvestorAssessment;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * One user, every route that binds a model carrying a `user_id`, somebody else's
 * record. All of them must answer 404 and change nothing.
 *
 * These exist so the ownership guarantee is asserted at the routes themselves
 * rather than inferred from the controllers. The controllers used to re-derive
 * the check by hand and each copy was one edit away from being wrong or absent;
 * `ScopedToOwner` moved it into route model binding, and this is the evidence
 * that the move actually covers every route it was supposed to.
 */
function otherUsersRecords(): array
{
    $owner = User::factory()->withModules()->create();
    $goldAsset = InvestmentAsset::query()->where('slug', 'gold')->firstOrFail();
    $costCategory = Category::factory()->cost()->create();

    return [
        'owner' => $owner,
        'transaction' => Transaction::factory()->cost()->create(['user_id' => $owner->id]),
        'bill' => $owner->bills()->create([
            'title' => 'Rent',
            'amount' => 5000000,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 1,
            'category_id' => $costCategory->id,
        ]),
        'budget' => Budget::factory()->create(['user_id' => $owner->id]),
        'investment' => Investment::query()->create([
            'user_id' => $owner->id,
            'investment_asset_id' => $goldAsset->id,
            'asset_type' => 'gold',
            'kind' => 'buy',
            'quantity' => 3,
            'cost_basis' => 4000000,
            'cost_basis_currency' => 'toman',
            'occurred_at' => '2026-07-01',
        ]),
        'goal' => SavingsGoal::factory()->create(['user_id' => $owner->id]),
        'assessment' => InvestorAssessment::factory()->create(['user_id' => $owner->id]),
        'recommendation' => AdvisorRecommendation::factory()->create(['user_id' => $owner->id]),
    ];
}

test('no route hands one user another user\'s record', function () {
    $records = otherUsersRecords();
    $intruder = User::factory()->withModules()->create();

    $routes = [
        ['patch',  route('transactions.update', $records['transaction'])],
        ['delete', route('transactions.destroy', $records['transaction'])],
        ['patch',  route('bills.update', $records['bill'])],
        ['delete', route('bills.destroy', $records['bill'])],
        ['patch',  route('budgets.update', $records['budget'])],
        ['delete', route('budgets.destroy', $records['budget'])],
        ['patch',  route('investments.update', $records['investment'])],
        ['delete', route('investments.destroy', $records['investment'])],
        ['patch',  route('savings-goals.update', $records['goal'])],
        ['delete', route('savings-goals.destroy', $records['goal'])],
        ['post',   route('savings-goals.achieved', $records['goal'])],
        ['get',    route('advisor.assessments.show', $records['assessment'])],
        ['post',   route('advisor.assessments.complete', $records['assessment'])],
        ['get',    route('advisor.recommendations.show', $records['recommendation'])],
    ];

    foreach ($routes as [$method, $url]) {
        $this->actingAs($intruder)
            ->{$method}($url)
            // 404 rather than 403 throughout: confirming the record exists is the
            // only thing a 403 would add, and it is the one thing worth withholding.
            ->assertNotFound();
    }

    // Nothing was touched on the way past.
    expect(Transaction::query()->whereKey($records['transaction']->getKey())->exists())->toBeTrue()
        ->and(Bill::query()->whereKey($records['bill']->getKey())->exists())->toBeTrue()
        ->and(Budget::query()->whereKey($records['budget']->getKey())->exists())->toBeTrue()
        ->and(Investment::query()->whereKey($records['investment']->getKey())->exists())->toBeTrue()
        ->and(SavingsGoal::query()->whereKey($records['goal']->getKey())->exists())->toBeTrue();
});

test('the api rejects another user\'s transaction the same way the web does', function () {
    $records = otherUsersRecords();
    $intruder = User::factory()->withModules()->create();

    foreach ([['getJson', 'show'], ['deleteJson', 'destroy']] as [$call, $action]) {
        $this->actingAs($intruder, 'sanctum')
            ->{$call}(route("api.transactions.{$action}", $records['transaction']))
            ->assertNotFound();
    }
});

test('the scope resolves for the owner and nobody else, on every model that carries it', function () {
    $records = otherUsersRecords();
    $intruder = User::factory()->withModules()->create();

    // Asserted against the binding itself rather than through a route, so the
    // answer is about ownership alone and not about whichever feature gate or
    // profile check happens to sit in front of a given page.
    $bound = [
        $records['transaction'],
        $records['bill'],
        $records['budget'],
        $records['investment'],
        $records['goal'],
        $records['assessment'],
        $records['recommendation'],
    ];

    foreach ($bound as $model) {
        $fresh = $model->newInstance();

        Auth::login($records['owner']);
        expect($fresh->resolveRouteBinding($model->getRouteKey()))
            ->not->toBeNull(sprintf('%s should resolve for its owner', $model::class));

        Auth::login($intruder);
        expect($fresh->resolveRouteBinding($model->getRouteKey()))
            ->toBeNull(sprintf('%s must not resolve for a stranger', $model::class));

        Auth::logout();
        expect($fresh->resolveRouteBinding($model->getRouteKey()))
            ->toBeNull(sprintf('%s must not resolve for a guest', $model::class));
    }
});

test('the owner can still act on their own record', function () {
    $records = otherUsersRecords();

    // The scope has to exclude a stranger and no more — a guard that also locked
    // the owner out would satisfy every negative test above.
    $this->actingAs($records['owner'])
        ->delete(route('transactions.destroy', $records['transaction']))
        ->assertRedirect();

    expect(Transaction::query()->whereKey($records['transaction']->getKey())->exists())->toBeFalse();
});
