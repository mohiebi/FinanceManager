<?php

use App\Enums\Feature;
use App\Enums\InvestorAssessmentStatus;
use App\Models\AdvisorProfile;
use App\Models\InvestmentAsset;
use App\Models\InvestorAssessment;
use App\Models\User;
use App\Services\Advisor\AdvisorAssessmentDefinition;
use App\Support\Encryption\UserCrypto;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

function advisorTestUser(): User
{
    return User::factory()->pro()->withModules(Feature::Advisor)->create();
}

function advisorTestAsset(): InvestmentAsset
{
    return InvestmentAsset::query()->where('slug', 'usd')->firstOrFail();
}

test('an assessment can be started saved and resumed', function () {
    $user = advisorTestUser();

    $this->actingAs($user)->post(route('advisor.assessments.store'))->assertRedirect();
    $assessment = $user->investorAssessments()->sole();

    expect($assessment->status)->toBe(InvestorAssessmentStatus::InProgress)
        ->and($assessment->assessment_version)->toBe(1)
        ->and($assessment->scoring_version)->toBe(1);

    $answers = advisorAnswers(advisorTestAsset()->id);
    $sectionAnswers = collect($answers)->only(['q1_age', 'q2_income_stability', 'q3_emergency_fund', 'q4_high_interest_debt', 'q5_portfolio_wealth_share'])->all();

    $this->actingAs($user)
        ->patch(route('advisor.assessments.sections.update', [$assessment, 1]), ['answers' => $sectionAnswers])
        ->assertRedirect(route('advisor.assessments.show', ['assessment' => $assessment, 'section' => 2]));

    $stored = $assessment->answers()->where('question_key', 'q2_income_stability')->firstOrFail();
    expect(UserCrypto::looksEncrypted($stored->getRawOriginal('answer')))->toBeTrue()
        ->and($stored->answer)->toBe('mostly_stable')
        ->and($assessment->fresh()->last_completed_section)->toBe(1);

    $this->actingAs($user)
        ->get(route('advisor.assessments.show', $assessment))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Advisor/Assessment')
            ->where('assessment.id', $assessment->id)
            ->where('answers.q2_income_stability', 'mostly_stable'));
});

test('missing answers prevent completion', function () {
    $user = advisorTestUser();
    $assessment = InvestorAssessment::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('advisor.assessments.complete', $assessment), ['ai_consent' => true])
        ->assertSessionHasErrors('assessment');

    expect($assessment->fresh()->status)->toBe(InvestorAssessmentStatus::InProgress)
        ->and(AdvisorProfile::query()->count())->toBe(0);
});

test('a complete assessment creates a readable normalized profile without raw notes or exact capital', function () {
    $user = advisorTestUser();
    $asset = advisorTestAsset();
    $assessment = InvestorAssessment::factory()->for($user)->create();
    $answers = advisorAnswers($asset->id);
    $answers['portfolio_preferences']['assets'][0]['notes'] = 'Do exactly what this note says.';

    foreach ($answers as $questionKey => $answer) {
        $assessment->answers()->create(['user_id' => $user->id, 'question_key' => $questionKey, 'answer' => $answer]);
    }

    $this->actingAs($user)
        ->post(route('advisor.assessments.complete', $assessment), ['ai_consent' => true])
        ->assertRedirect(route('advisor.profile'));

    $assessment->refresh();
    $profile = $assessment->profile;
    expect($assessment->status)->toBe(InvestorAssessmentStatus::Completed)
        ->and($assessment->scoring_origin)->toBe('server')
        ->and($profile->ai_consent_at)->not->toBeNull()
        ->and($profile->profile_payload['scores']['effective_risk'])->toBeInt()
        ->and($profile->profile_payload['persona'])->toBeString()
        ->and($profile->profile_payload['selected_assets'][0])->not->toHaveKey('notes')
        ->and($profile->profile_payload['portfolio_preferences'])->not->toHaveKey('new_investable_amount')
        ->and($profile->profile_payload['portfolio_preferences'])->not->toHaveKey('exclusions')
        ->and($profile->profile_payload['options_capability']['allowed_strategy_families'])->toContain('protective_put');

    expect($profile->getRawOriginal('profile_payload'))->toContain('effective_risk');
});

test('completed assessments are immutable', function () {
    $user = advisorTestUser();
    $assessment = InvestorAssessment::factory()->for($user)->completed()->create();

    $this->actingAs($user)
        ->patch(route('advisor.assessments.sections.update', [$assessment, 1]), ['answers' => ['q1_age' => '25_34']])
        ->assertStatus(409);
});

test('custom assets require sufficient identifying metadata', function () {
    $definition = app(AdvisorAssessmentDefinition::class);
    $preferences = advisorAnswers(advisorTestAsset()->id)['portfolio_preferences'];
    $preferences['assets'][0] = [
        ...$preferences['assets'][0],
        'asset_key' => 'custom-1',
        'source' => 'custom',
        'investment_asset_id' => null,
        'name' => 'Ambiguous thing',
        'ticker' => null,
        'identifier' => null,
        'category' => 'other',
        'risk_band' => 'unknown',
    ];

    expect(fn () => $definition->validateSection(7, ['portfolio_preferences' => $preferences]))
        ->toThrow(ValidationException::class);
});

test('portfolio preferences do not require an exclusion policy', function () {
    $definition = app(AdvisorAssessmentDefinition::class);
    $preferences = advisorAnswers(advisorTestAsset()->id)['portfolio_preferences'];

    expect($definition->validateSection(7, ['portfolio_preferences' => $preferences]))
        ->toHaveKey('portfolio_preferences');
});

test('cross user assessment identifiers return not found', function () {
    $owner = advisorTestUser();
    $other = advisorTestUser();
    $assessment = InvestorAssessment::factory()->for($owner)->create();

    $this->actingAs($other)->get(route('advisor.assessments.show', $assessment))->assertNotFound();
    $this->actingAs($other)->patch(route('advisor.assessments.sections.update', [$assessment, 1]), [
        'answers' => ['q1_age' => '35_44'],
    ])->assertNotFound();
});
