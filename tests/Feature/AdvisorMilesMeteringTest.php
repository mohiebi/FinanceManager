<?php

use App\Actions\Miles\AdjustMiles;
use App\Actions\Miles\ChargeAdvisorAssessment;
use App\Actions\Miles\MeterAdvisorConsultation;
use App\Actions\Miles\RecordServiceUsage;
use App\Actions\Miles\ReserveAdvisorMiles;
use App\Actions\Miles\SettleAdvisorMiles;
use App\Enums\AdvisorRecommendationStatus;
use App\Enums\MilesReason;
use App\Models\AdvisorProfile;
use App\Models\AdvisorRecommendation;
use App\Models\InvestorAssessment;
use App\Models\ServiceUsageEvent;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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

it('keeps one assessment completion free in each rolling thirty day window', function () {
    config()->set('miles.advisor_charging', true);
    $user = User::factory()->create();
    app(AdjustMiles::class)($user, 50, MilesReason::AdminAdjustment, 'seed');
    $first = InvestorAssessment::factory()->for($user)->create(['completed_at' => now()->subDays(10)]);
    $second = InvestorAssessment::factory()->for($user)->create();

    $firstMetering = app(ChargeAdvisorAssessment::class)($first);
    $secondMetering = app(ChargeAdvisorAssessment::class)($second);

    expect($firstMetering)->toBe(['quoted' => 0, 'charged' => 0])
        ->and($secondMetering)->toBe(['quoted' => 25, 'charged' => 25])
        ->and($user->mileWallet()->first()->balance)->toBe(25);
});

it('refunds a failed consultation reservation', function () {
    config()->set('miles.advisor_charging', true);
    $user = User::factory()->create();
    app(AdjustMiles::class)($user, 30, MilesReason::AdminAdjustment, 'seed');
    $recommendation = AdvisorRecommendation::factory()->for($user)->create();
    $message = $recommendation->messages()->create([
        'user_id' => $user->id,
        'role' => 'user',
        'payload' => ['content' => 'Question'],
    ]);

    $charged = app(MeterAdvisorConsultation::class)->reserve($message);
    app(MeterAdvisorConsultation::class)->refund($message, $charged);

    expect($charged)->toBe(15)
        ->and($user->mileWallet()->first()->balance)->toBe(30);
});

it('presents assessment recommendation and consultation prices before use', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('advisor.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('assessmentPricing.miles', 0)
            ->where('assessmentPricing.charging', false));

    $assessment = InvestorAssessment::factory()->completed()->for($user)->create();
    $profile = AdvisorProfile::factory()->for($user)->create([
        'investor_assessment_id' => $assessment->getKey(),
    ]);

    $this->actingAs($user)
        ->get(route('advisor.profile'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recommendationPricing.full_miles', 175)
            ->where('recommendationPricing.guidance_miles', 80)
            ->where('recommendationPricing.charging', false));

    $recommendation = AdvisorRecommendation::factory()->for($user)->create([
        'advisor_profile_id' => $profile->getKey(),
        'miles_outcome' => 'full',
    ]);

    $this->actingAs($user)
        ->get(route('advisor.profile'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recommendationPricing.full_miles', 250));

    $this->actingAs($user)
        ->get(route('advisor.recommendations.show', $recommendation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('consultationPricing.miles', 15)
            ->where('recommendation.quoted_miles', 0)
            ->where('recommendation.charged_miles', 0));
});

it('refunds in full when the vault holding window closes before the user returns', function () {
    config()->set('miles.advisor_charging', true);
    $user = User::factory()->create();
    app(AdjustMiles::class)($user, 300, MilesReason::AdminAdjustment, 'seed');
    $recommendation = AdvisorRecommendation::factory()->for($user)->create([
        'status' => AdvisorRecommendationStatus::Generating,
    ]);

    $reserved = app(ReserveAdvisorMiles::class)($recommendation);
    expect($user->mileWallet()->first()->balance)->toBe(125);

    // The generation succeeded, so nothing here is a job failure — the browser
    // simply never came back inside advisor.pending_payload_lifetime and the
    // plaintext is gone. Charging for a result nobody can ever open would be
    // taking Miles for nothing.
    $settled = app(SettleAdvisorMiles::class)($reserved, 'failure');

    expect($settled->charged_miles)->toBe(0)
        ->and($settled->miles_outcome)->toBe('failure')
        ->and($user->mileWallet()->first()->balance)->toBe(300);
});
