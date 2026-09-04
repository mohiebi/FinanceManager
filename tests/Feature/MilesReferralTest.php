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
