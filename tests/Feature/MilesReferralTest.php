<?php

use App\Actions\Miles\AdjustMiles;
use App\Actions\Miles\AttributeReferral;
use App\Actions\Miles\EvaluateReferralRewards;
use App\Actions\Miles\GiftMiles;
use App\Enums\MilesReason;
use App\Enums\ReferralStage;
use App\Models\MileDay;
use App\Models\MileWallet;
use App\Models\NoSpendDay;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\User;
use App\Support\AcquisitionSource;
use Illuminate\Http\Request;

it('locks the first valid referral touch to a newly created account', function () {
    $referrer = User::factory()->create();
    $wallet = MileWallet::factory()->for($referrer)->create();
    $request = Request::create('/?ref='.$wallet->referral_code);
    $request->setLaravelSession(app('session.store'));
    AcquisitionSource::capture($request);
    app()->instance('request', $request);

    $friend = User::factory()->create();
    $referral = app(AttributeReferral::class)($friend);

    expect($referral)->not->toBeNull()
        ->and($referral->referrer_id)->toBe($referrer->id)
        ->and($referral->referred_user_id)->toBe($friend->id)
        ->and(app(AttributeReferral::class)($friend)->id)->toBe($referral->id);
});

it('awards each referral stage only once', function () {
    $referrer = User::factory()->create();
    $friend = User::factory()->create(['created_at' => now()->subDays(10)]);
    $referral = Referral::query()->create([
        'referrer_id' => $referrer->id,
        'referred_user_id' => $friend->id,
        'code' => 'TESTREFERRAL',
        'status' => 'pending',
        'attributed_at' => now()->subDays(10),
    ]);
    NoSpendDay::withoutEvents(fn () => NoSpendDay::query()->create([
        'user_id' => $friend->id,
        'date' => now()->toDateString(),
    ]));

    foreach (range(1, 7) as $offset) {
        MileDay::query()->create([
            'user_id' => $friend->id,
            'local_date' => now()->subDays($offset)->toDateString(),
            'timezone' => 'UTC',
            'activity_miles' => 2,
        ]);
    }

    app(EvaluateReferralRewards::class)($friend);
    app(EvaluateReferralRewards::class)($friend);

    expect($referral->rewards()->count())->toBe(2)
        ->and($referrer->mileWallet()->first()->balance)->toBe(50)
        ->and($friend->mileWallet()->first()->balance)->toBe(30);
});

it('moves gifted Miles without creating new supply', function () {
    $sender = User::factory()->create();
    $friend = User::factory()->create();
    $referral = Referral::query()->create([
        'referrer_id' => $sender->id,
        'referred_user_id' => $friend->id,
        'code' => 'ACTIVEFRIEND',
        'status' => 'active',
        'attributed_at' => now(),
    ]);
    ReferralReward::query()->create([
        'referral_id' => $referral->id,
        'stage' => ReferralStage::Activated,
        'awarded_at' => now(),
    ]);
    app(AdjustMiles::class)($sender, 100, MilesReason::AdminAdjustment, 'seed');

    app(GiftMiles::class)($sender, $friend, 50);

    expect($sender->mileWallet()->first()->balance)->toBe(50)
        ->and($friend->mileWallet()->first()->balance)->toBe(50)
        ->and($sender->mileWallet()->first()->balance + $friend->mileWallet()->first()->balance)->toBe(100);
});

/** A friend who already clears every stage gate except the referral's own status. */
function qualifiedFriend(User $referrer, string $status = 'pending', ?string $reviewReason = null): array
{
    $friend = User::factory()->create(['created_at' => now()->subDays(10)]);
    $referral = Referral::query()->create([
        'referrer_id' => $referrer->id,
        'referred_user_id' => $friend->id,
        'code' => 'TESTREFERRAL'.$friend->id,
        'status' => $status,
        'review_reason' => $reviewReason,
        'attributed_at' => now()->subDays(10),
    ]);
    NoSpendDay::withoutEvents(fn () => NoSpendDay::query()->create([
        'user_id' => $friend->id,
        'date' => now()->toDateString(),
    ]));

    foreach (range(1, 3) as $offset) {
        MileDay::query()->create([
            'user_id' => $friend->id,
            'local_date' => now()->subDays($offset)->toDateString(),
            'timezone' => 'UTC',
            'activity_miles' => 2,
        ]);
    }

    return [$friend, $referral];
}

it('refuses to attribute a referral to the referrer themselves', function () {
    $referrer = User::factory()->create();
    $wallet = MileWallet::factory()->for($referrer)->create();
    $request = Request::create('/?ref='.$wallet->referral_code);
    $request->setLaravelSession(app('session.store'));
    AcquisitionSource::capture($request);
    app()->instance('request', $request);

    expect(app(AttributeReferral::class)($referrer))->toBeNull()
        ->and(Referral::query()->count())->toBe(0);
});

it('pays nothing while a referral is held for review', function () {
    $referrer = User::factory()->create();
    [$friend, $referral] = qualifiedFriend($referrer, 'review', 'same_device');

    app(EvaluateReferralRewards::class)($friend);

    // Held, not rejected — the friend is not punished for a flag on the sender,
    // but nothing is paid out until a human clears it.
    expect($referral->rewards()->count())->toBe(0)
        ->and($referrer->mileWallet()->first()?->balance ?? 0)->toBe(0)
        ->and($friend->mileWallet()->first()?->balance ?? 0)->toBe(0);
});

it('stops paying the referrer at the rolling cap while the friend still earns', function () {
    $referrer = User::factory()->create();
    $cap = (int) config('miles.referrals.rolling_cap');

    // Sitting just under the cap: the activated stage's 25 would breach it.
    app(AdjustMiles::class)($referrer, $cap - 10, MilesReason::Referral, 'prior-referrals');

    [$friend, $referral] = qualifiedFriend($referrer);

    app(EvaluateReferralRewards::class)($friend);

    $activated = $referral->rewards()->where('stage', ReferralStage::Activated)->sole();

    expect($referrer->mileWallet()->first()->balance)->toBe($cap - 10)
        ->and($activated->referrer_ledger_entry_id)->toBeNull()
        ->and($activated->friend_ledger_entry_id)->not->toBeNull()
        ->and($friend->mileWallet()->first()->balance)->toBe(15);
});

it('remembers a referral from a shared invite link and keeps the first touch', function () {
    $referrer = User::factory()->create();
    $wallet = MileWallet::factory()->for($referrer)->create();
    $other = MileWallet::factory()->for(User::factory()->create())->create();

    $this->get(route('invite', ['code' => $wallet->referral_code]))
        ->assertRedirect(route('home'))
        ->assertSessionHas(AcquisitionSource::REFERRAL_SESSION_KEY);

    // A second link cannot overwrite the first — first touch means first.
    $this->get(route('invite', ['code' => $other->referral_code]))->assertRedirect(route('home'));

    expect(session(AcquisitionSource::REFERRAL_SESSION_KEY)['code'])
        ->toBe($wallet->referral_code);
});

it('identifies a device by its private token instead of its shared user agent', function () {
    $referrer = User::factory()->create();
    $wallet = MileWallet::factory()->for($referrer)->create();
    $server = ['HTTP_USER_AGENT' => 'Mozilla/5.0 Common Browser'];

    $first = Request::create(
        '/?ref='.$wallet->referral_code,
        'GET',
        cookies: [AcquisitionSource::DEVICE_COOKIE => str_repeat('a', 64)],
        server: $server,
    );
    $first->setLaravelSession(app('session.store'));
    AcquisitionSource::capture($first);
    $firstHash = $first->session()->pull(AcquisitionSource::REFERRAL_SESSION_KEY)['device_hash'];

    $second = Request::create(
        '/?ref='.$wallet->referral_code,
        'GET',
        cookies: [AcquisitionSource::DEVICE_COOKIE => str_repeat('b', 64)],
        server: $server,
    );
    $second->setLaravelSession(app('session.store'));
    AcquisitionSource::capture($second);
    $secondHash = $second->session()->pull(AcquisitionSource::REFERRAL_SESSION_KEY)['device_hash'];

    $sameDevice = Request::create(
        '/?ref='.$wallet->referral_code,
        'GET',
        cookies: [AcquisitionSource::DEVICE_COOKIE => str_repeat('a', 64)],
        server: ['HTTP_USER_AGENT' => 'A completely different browser'],
    );
    $sameDevice->setLaravelSession(app('session.store'));
    AcquisitionSource::capture($sameDevice);
    $sameDeviceHash = $sameDevice->session()->pull(AcquisitionSource::REFERRAL_SESSION_KEY)['device_hash'];

    expect($firstHash)->not->toBe($secondHash)
        ->and($sameDeviceHash)->toBe($firstHash);
});

it('lands an unknown invite code on the marketing page without remembering it', function () {
    $this->get(route('invite', ['code' => 'NOTAREALCODE']))
        ->assertRedirect(route('home'))
        ->assertSessionMissing(AcquisitionSource::REFERRAL_SESSION_KEY);
});

it('never attributes an invite link to somebody already signed in', function () {
    $referrer = User::factory()->create();
    $wallet = MileWallet::factory()->for($referrer)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('invite', ['code' => $wallet->referral_code]))
        ->assertRedirect(route('home'))
        ->assertSessionMissing(AcquisitionSource::REFERRAL_SESSION_KEY);
});

it('keeps a referral touch after the session that captured it has expired', function () {
    $referrer = User::factory()->create();
    $wallet = MileWallet::factory()->for($referrer)->create();

    $response = $this->get(route('invite', ['code' => $wallet->referral_code]));
    // Decrypted, because the request bag the action reads is post-middleware.
    $cookie = $response->getCookie(AcquisitionSource::REFERRAL_COOKIE);

    expect($cookie)->not->toBeNull()
        // Thirty days, not the two-hour session that used to carry it alone.
        ->and($cookie->getExpiresTime())
        ->toBeGreaterThan(now()->addDays(29)->getTimestamp());

    // A fresh session, as if they came back the next evening.
    session()->flush();
    request()->cookies->set(AcquisitionSource::REFERRAL_COOKIE, $cookie->getValue());

    $friend = User::factory()->create();
    $referral = app(AttributeReferral::class)($friend);

    expect($referral)->not->toBeNull()
        ->and($referral->referrer_id)->toBe($referrer->id);
});
