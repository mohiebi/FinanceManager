<?php

use App\Actions\Miles\AdjustMiles;
use App\Actions\Miles\RecordServiceUsage;
use App\Actions\Miles\ReserveAdvisorMiles;
use App\Actions\Miles\SettleAdvisorMiles;
use App\Enums\AdvisorRecommendationStatus;
use App\Enums\MilesReason;
use App\Models\AdvisorRecommendation;
use App\Models\ServiceUsageEvent;
use App\Models\User;

it('quotes recommendations without debiting during shadow mode', function () {
    $user = User::factory()->create();
    $recommendation = AdvisorRecommendation::factory()->for($user)->create([
        'status' => AdvisorRecommendationStatus::Generating,
    ]);

    $recommendation = app(ReserveAdvisorMiles::class)($recommendation);

    expect($recommendation->quoted_miles)->toBe(175)
        ->and($recommendation->reserved_miles)->toBe(0)
        ->and($user->mileWallet()->doesntExist())->toBeTrue();
});

it('reserves the full price and refunds the guidance difference', function () {
    config()->set('miles.advisor_charging', true);
    $user = User::factory()->create();
    app(AdjustMiles::class)($user, 300, MilesReason::AdminAdjustment, 'seed');
    $recommendation = AdvisorRecommendation::factory()->for($user)->create([
        'status' => AdvisorRecommendationStatus::Generating,
    ]);

    $reserved = app(ReserveAdvisorMiles::class)($recommendation);
    $settled = app(SettleAdvisorMiles::class)($reserved, 'guidance');

    expect($settled->quoted_miles)->toBe(175)
        ->and($settled->reserved_miles)->toBe(175)
        ->and($settled->charged_miles)->toBe(80)
        ->and($settled->miles_outcome)->toBe('guidance')
        ->and($user->mileWallet()->first()->balance)->toBe(220);
});

it('uses the later price only after a full recommendation', function () {
    config()->set('miles.advisor_charging', true);
    $user = User::factory()->create();
    app(AdjustMiles::class)($user, 600, MilesReason::AdminAdjustment, 'seed');
    $first = AdvisorRecommendation::factory()->for($user)->create([
        'status' => AdvisorRecommendationStatus::Generating,
    ]);
    app(SettleAdvisorMiles::class)(app(ReserveAdvisorMiles::class)($first), 'full');
    $second = AdvisorRecommendation::factory()->for($user)->create([
        'status' => AdvisorRecommendationStatus::Generating,
    ]);

    $second = app(ReserveAdvisorMiles::class)($second);

    expect($second->quoted_miles)->toBe(250)
        ->and($second->reserved_miles)->toBe(250);
});

it('records token usage with the configured rate snapshot', function () {
    config()->set('miles.advisor.provider_rates.openai.test-model', [
        'prompt' => 2.0,
        'completion' => 8.0,
    ]);
    $user = User::factory()->create();
    $response = (object) [
        'meta' => (object) ['provider' => 'openai', 'model' => 'test-model'],
        'usage' => (object) ['promptTokens' => 1000, 'completionTokens' => 500],
    ];

    app(RecordServiceUsage::class)(
        $user,
        'recommendation',
        'success',
        $response,
        null,
        hrtime(true),
        175,
    );

    $event = ServiceUsageEvent::query()->firstOrFail();
    expect($event->prompt_tokens)->toBe(1000)
        ->and($event->completion_tokens)->toBe(500)
        ->and((float) $event->provider_cost_usd)->toBe(0.006)
        ->and($event->metadata['rate_snapshot'])->toBe(['prompt' => 2, 'completion' => 8]);
});
