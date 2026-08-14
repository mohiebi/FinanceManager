<?php

use App\Actions\Billing\GrantProAccess;
use App\Actions\Billing\RevokeProAccess;
use App\Enums\Feature;
use App\Enums\FeatureTier;
use App\Enums\GrantReason;
use App\Models\SubscriptionGrant;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('a user is pro only while pro_until is in the future', function () {
    expect(User::factory()->create()->isPro())->toBeFalse()
        ->and(User::factory()->proExpired()->create()->isPro())->toBeFalse()
        ->and(User::factory()->pro()->create()->isPro())->toBeTrue();
});

test('granting months makes a user pro and records why', function () {
    $user = User::factory()->create();
    $payment = SubscriptionPayment::factory()->confirmed()->create(['user_id' => $user->id]);

    $grant = app(GrantProAccess::class)($user, 3, GrantReason::Payment, paymentId: $payment->id);

    expect($user->isPro())->toBeTrue()
        ->and($user->fresh()->isPro())->toBeTrue()
        ->and($grant->months)->toBe(3)
        ->and($grant->reason)->toBe(GrantReason::Payment)
        ->and($grant->pro_until_before)->toBeNull()
        ->and($grant->subscription_payment_id)->toBe($payment->id)
        ->and($grant->pro_until_after->toDateTimeString())
        ->toBe(now()->addMonthsNoOverflow(3)->toDateTimeString());
});

test('a grant cannot point at a payment that does not exist', function () {
    $user = User::factory()->create();

    // The foreign key is the guard: an audit row citing a payment nobody can
    // look up would make the trail worse than useless.
    expect(fn () => app(GrantProAccess::class)(
        $user, 1, GrantReason::Payment, paymentId: '01jq0000000000000000000000'
    ))->toThrow(QueryException::class);

    expect($user->fresh()->isPro())->toBeFalse();
});

test('paying again while still active extends from the current expiry, not from today', function () {
    $user = User::factory()->create();
    $this->travelTo('2026-03-01 10:00:00');

    app(GrantProAccess::class)($user, 1, GrantReason::Payment);
    $afterFirst = $user->fresh()->pro_until;

    // Ten days in, with three weeks still on the clock.
    $this->travelTo('2026-03-11 10:00:00');
    app(GrantProAccess::class)($user, 1, GrantReason::Payment);

    expect($user->fresh()->pro_until->toDateTimeString())
        ->toBe($afterFirst->copy()->addMonthsNoOverflow(1)->toDateTimeString())
        ->and($user->fresh()->pro_until->toDateTimeString())->toBe('2026-05-01 10:00:00')
        // The renewal must not have thrown away the three weeks already paid for.
        ->not->toBe(now()->addMonthsNoOverflow(1)->toDateTimeString());
});

test('a lapsed user extends from today rather than from their old expiry', function () {
    $this->travelTo('2026-03-20 10:00:00');
    $user = User::factory()->proExpired()->create();

    app(GrantProAccess::class)($user, 1, GrantReason::Payment);

    expect($user->fresh()->pro_until->toDateTimeString())->toBe('2026-04-20 10:00:00');
});

test('a month added to the 31st lands inside the next month', function () {
    $this->travelTo('2026-01-31 09:00:00');
    $user = User::factory()->create();

    app(GrantProAccess::class)($user, 1, GrantReason::Payment);

    expect($user->fresh()->pro_until->toDateString())->toBe('2026-02-28');
});

test('consecutive grants stack instead of overwriting each other', function () {
    $this->travelTo('2026-03-01 10:00:00');
    $user = User::factory()->create();

    app(GrantProAccess::class)($user, 1, GrantReason::Payment);
    app(GrantProAccess::class)($user, 3, GrantReason::Payment);

    expect($user->fresh()->pro_until->toDateTimeString())->toBe('2026-07-01 10:00:00')
        ->and($user->subscriptionGrants()->count())->toBe(2);
});

test('a grant of less than one month is refused', function () {
    $user = User::factory()->create();

    expect(fn () => app(GrantProAccess::class)($user, 0, GrantReason::AdminGrant))
        ->toThrow(InvalidArgumentException::class);

    expect($user->fresh()->pro_until)->toBeNull()
        ->and(SubscriptionGrant::query()->count())->toBe(0);
});

test('revoking ends pro immediately and leaves an audit row', function () {
    $user = User::factory()->pro()->create();
    $admin = User::factory()->create();

    $grant = app(RevokeProAccess::class)($user, $admin, 'Refunded by request.');

    expect($user->isPro())->toBeFalse()
        ->and($user->fresh()->isPro())->toBeFalse()
        // Not nulled: the row still records that they were Pro, and until when.
        ->and($user->fresh()->pro_until)->not->toBeNull()
        ->and($grant->months)->toBe(0)
        ->and($grant->reason)->toBe(GrantReason::AdminRevoke)
        ->and($grant->granted_by_admin_id)->toBe($admin->id)
        ->and($grant->note)->toBe('Refunded by request.');
});

test('pro_until cannot be mass assigned', function () {
    $user = User::factory()->create();

    // Strict mode is on app-wide, so this is a loud failure rather than a silently
    // ignored attribute — either way, no request payload can reach the entitlement.
    expect(fn () => $user->update(['pro_until' => now()->addYear()]))
        ->toThrow(MassAssignmentException::class);

    expect($user->fresh()->isPro())->toBeFalse();
});

test('no feature is pro yet, so a paying user gains no entitlement they lacked', function () {
    $free = User::factory()->create();
    $pro = User::factory()->pro()->create();

    foreach (Feature::cases() as $feature) {
        expect($feature->tier())->toBe(FeatureTier::Free)
            ->and($free->mayUse($feature))->toBeTrue()
            ->and($pro->mayUse($feature))->toBeTrue();
    }

    // The shared map the nav is built from must agree with mayUse() for both.
    foreach ([$free, $pro] as $user) {
        $map = $user->featureSet()->toArray($user->isPro());

        foreach (Feature::cases() as $feature) {
            expect($map[$feature->value]['tier'])->toBe('free')
                ->and($map[$feature->value]['may_use'])->toBeTrue();
        }
    }
});

test('the shared subscription prop reports entitlement without an extra query', function () {
    $user = User::factory()->pro()->create();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $response = $this->actingAs($user)->get(route('dashboard'));
    $withSubscription = $queries;

    $response->assertOk();

    $props = $response->viewData('page')['props'];

    expect($props['subscription']['is_pro'])->toBeTrue()
        ->and($props['subscription']['pro_until'])->toBe($user->pro_until->toIso8601String());

    // Reading it again must not touch the database: pro_until rides along on the
    // auth user, which is the whole reason it is a column.
    $before = $withSubscription;
    expect($user->isPro())->toBeTrue()
        ->and($queries)->toBe($before);
});

test('the shared prop mirrors the billing kill switch', function () {
    $user = User::factory()->create();

    // Both directions asserted from config set here rather than inherited from
    // .env — a developer testing against a live billing setup must not change
    // what this proves.
    config()->set('billing.enabled', false);
    expect($this->actingAs($user)->get(route('dashboard'))
        ->viewData('page')['props']['subscription']['billing_enabled'])->toBeFalse();

    config()->set('billing.enabled', true);
    expect($this->actingAs($user)->get(route('dashboard'))
        ->viewData('page')['props']['subscription']['billing_enabled'])->toBeTrue();
});

test('guests get no subscription prop at all', function () {
    $props = $this->get(route('login'))->viewData('page')['props'];

    expect($props['subscription'])->toBeNull();
});

test('the console can sell pro by hand before any payment page exists', function () {
    $user = User::factory()->create(['email' => 'buyer@example.com']);

    $this->artisan('billing:grant', [
        'email' => 'buyer@example.com',
        'months' => 12,
        '--note' => 'Paid by bank transfer.',
    ])->assertExitCode(0);

    $grant = $user->fresh()->subscriptionGrants()->sole();

    expect($user->fresh()->isPro())->toBeTrue()
        ->and($grant->months)->toBe(12)
        ->and($grant->reason)->toBe(GrantReason::AdminGrant)
        ->and($grant->note)->toBe('Paid by bank transfer.');

    $this->artisan('billing:revoke', ['email' => 'buyer@example.com'])->assertExitCode(0);

    expect($user->fresh()->isPro())->toBeFalse();
});

test('the console commands refuse unknown users and nonsense spans', function () {
    User::factory()->create(['email' => 'buyer@example.com']);

    $this->artisan('billing:grant', ['email' => 'nobody@example.com', 'months' => 1])
        ->assertExitCode(1);

    $this->artisan('billing:grant', ['email' => 'buyer@example.com', 'months' => 0])
        ->assertExitCode(1);

    $this->artisan('billing:revoke', ['email' => 'nobody@example.com'])
        ->assertExitCode(1);

    expect(SubscriptionGrant::query()->count())->toBe(0);
});
