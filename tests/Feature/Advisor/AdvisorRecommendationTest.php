<?php

use App\Ai\Agents\AdvisorRecommendationAgent;
use App\Enums\AdvisorRecommendationStatus;
use App\Enums\Feature;
use App\Models\AdvisorProfile;
use App\Models\InvestmentAsset;
use App\Models\InvestorAssessment;
use App\Models\User;
use App\Services\Advisor\AdvisorProposalValidator;
use App\Support\Encryption\UserCrypto;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

function advisorRecommendationProfile(User $user): AdvisorProfile
{
    $usd = InvestmentAsset::query()->where('slug', 'usd')->firstOrFail();
    $bitcoin = InvestmentAsset::query()->where('slug', 'bitcoin')->firstOrFail();
    $assessment = InvestorAssessment::factory()->for($user)->completed()->create();

    return AdvisorProfile::query()->create([
        'user_id' => $user->id,
        'investor_assessment_id' => $assessment->id,
        'profile_version' => 1,
        'ai_consent_at' => now(),
        'profile_payload' => [
            'profile_version' => 1,
            'persona' => 'strategic_growth_investor',
            'risk_band' => 'growth',
            'scores' => [
                'effective_risk' => 65, 'risk_willingness' => 75, 'risk_capacity' => 70,
                'financial_resilience' => 75, 'liquidity_need' => 20, 'investment_knowledge' => 70,
                'behavioral_stability' => 75, 'loss_aversion' => 30, 'return_ambition' => 70, 'time_horizon' => 95,
            ],
            'maximum_tolerated_drawdown' => 30,
            'financial_context' => ['income_stability' => 'mostly_stable', 'emergency_fund' => '6_12', 'high_interest_debt' => 'none', 'portfolio_share_of_liquid_wealth' => '25_50'],
            'goals' => ['primary' => 'long_term_wealth', 'importance' => 'important', 'time_horizon' => '10_plus', 'early_withdrawal_likelihood' => 'unlikely', 'liquidity' => ['proportion' => 'under_10', 'speed' => 'within_month'], 'target_return' => '8_12'],
            'portfolio_preferences' => ['scope' => 'both', 'primary_currency' => 'TOMAN', 'country' => 'US', 'markets' => ['Global'], 'tax_sensitive' => false],
            'selected_assets' => [
                ['asset_key' => 'cash', 'source' => 'cashpilot', 'investment_asset_id' => $usd->id, 'name' => 'US Dollar', 'ticker' => 'USD', 'identifier' => null, 'exchange_or_market' => null, 'country' => 'US', 'currency' => 'USD', 'category' => 'currency', 'risk_band' => 'defensive', 'liquidity' => 'same_day', 'perspective' => 'neutral', 'conviction' => 'medium', 'holding_period' => '10_plus', 'inclusion' => 'required'],
                ['asset_key' => 'bitcoin', 'source' => 'cashpilot', 'investment_asset_id' => $bitcoin->id, 'name' => 'Bitcoin', 'ticker' => 'BTC', 'identifier' => null, 'exchange_or_market' => null, 'country' => null, 'currency' => 'USD', 'category' => 'crypto', 'risk_band' => 'speculative', 'liquidity' => 'same_day', 'perspective' => 'bullish', 'conviction' => 'medium', 'holding_period' => '10_plus', 'inclusion' => 'allowed'],
            ],
            'options_capability' => ['willingness' => 'no', 'broker_access' => false, 'knowledge_score' => 0, 'experience_level' => 'none', 'allowed_underlying_categories' => [], 'allowed_strategy_families' => [], 'maximum_risk_budget_percent' => 0, 'monitoring_suitability' => 'not_applicable'],
            'constraints' => ['minimum_liquid_allocation' => 20, 'maximum_single_asset_allocation' => 100, 'maximum_high_risk_allocation' => 80, 'maximum_speculative_allocation' => 50, 'maximum_options_risk_budget' => 0, 'hard_caps' => []],
            'warnings' => [],
        ],
    ]);
}

function advisorContext(AdvisorProfile $profile): array
{
    $payload = $profile->profile_payload;

    return [
        'selected_assets' => $payload['selected_assets'],
        'portfolio_preferences' => $payload['portfolio_preferences'],
        'options_capability' => $payload['options_capability'],
        'constraints' => $payload['constraints'],
    ];
}

beforeEach(fn () => $this->withoutVite());

test('a valid structured AI recommendation is encrypted and stored ready', function () {
    $user = User::factory()->pro()->withModules(Feature::Advisor)->create();
    advisorRecommendationProfile($user);
    AdvisorRecommendationAgent::fake([advisorValidRecommendation()])->preventStrayPrompts();

    $response = $this->actingAs($user)->postJson(route('advisor.recommendations.store'))
        ->assertOk()
        ->assertJsonPath('status', 'ready')
        ->assertJsonPath('payload.status', 'recommendation_ready');

    $recommendation = $user->advisorRecommendations()->sole();
    expect($recommendation->status)->toBe(AdvisorRecommendationStatus::Ready)
        ->and($recommendation->provider_calls)->toBe(1)
        ->and($recommendation->repair_attempts)->toBe(0)
        ->and(UserCrypto::looksEncrypted($recommendation->getRawOriginal('recommendation_payload')))->toBeTrue()
        ->and($recommendation->recommendation_payload['primary']['allocations'])->toHaveCount(2)
        ->and($response->json('payload.transition_plan'))->toBeArray();
});

test('one invalid response receives one repair request', function () {
    $user = User::factory()->pro()->withModules(Feature::Advisor)->create();
    advisorRecommendationProfile($user);
    $invalid = advisorValidRecommendation();
    $invalid['primary']['allocations'][0]['target_percent'] = 60;
    AdvisorRecommendationAgent::fake([$invalid, advisorValidRecommendation()])->preventStrayPrompts();

    $this->actingAs($user)->postJson(route('advisor.recommendations.store'))
        ->assertOk()->assertJsonPath('status', 'ready');

    $recommendation = $user->advisorRecommendations()->sole();
    expect($recommendation->provider_calls)->toBe(2)
        ->and($recommendation->repair_attempts)->toBe(1)
        ->and($recommendation->status)->toBe(AdvisorRecommendationStatus::Ready);
});

test('a second invalid response fails safely', function () {
    $user = User::factory()->pro()->withModules(Feature::Advisor)->create();
    advisorRecommendationProfile($user);
    $invalid = advisorValidRecommendation();
    $invalid['primary']['allocations'][0]['target_percent'] = 60;
    AdvisorRecommendationAgent::fake([$invalid, $invalid])->preventStrayPrompts();

    $this->actingAs($user)->postJson(route('advisor.recommendations.store'))
        ->assertOk()->assertJsonPath('status', 'failed')->assertJsonPath('failure_code', 'validation_failed');

    expect($user->advisorRecommendations()->sole()->recommendation_payload)->toBeNull();
});

test('provider failures fail safely without persisting a plaintext response', function () {
    $user = User::factory()->pro()->withModules(Feature::Advisor)->create();
    advisorRecommendationProfile($user);
    Log::spy();
    AdvisorRecommendationAgent::fake(fn () => throw new RuntimeException('Provider timeout.'));

    $this->actingAs($user)->postJson(route('advisor.recommendations.store'))
        ->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('failure_code', 'provider_failure');

    $recommendation = $user->advisorRecommendations()->sole();
    expect($recommendation->status)->toBe(AdvisorRecommendationStatus::Failed)
        ->and($recommendation->recommendation_payload)->toBeNull()
        ->and($recommendation->provider_calls)->toBe(1);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Advisor AI provider call failed.'
            && $context['recommendation_id'] === $recommendation->id
            && $context['stage'] === 'recommendation'
            && $context['exception_class'] === RuntimeException::class
            && ! array_key_exists('exception_message', $context));
});

test('a blocked repair does not consume an unused repair attempt', function () {
    config()->set('advisor.max_provider_calls', 1);
    $user = User::factory()->pro()->withModules(Feature::Advisor)->create();
    advisorRecommendationProfile($user);
    $invalid = advisorValidRecommendation();
    $invalid['primary']['allocations'][0]['target_percent'] = 60;
    AdvisorRecommendationAgent::fake([$invalid])->preventStrayPrompts();

    $this->actingAs($user)->postJson(route('advisor.recommendations.store'))
        ->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('failure_code', 'provider_call_limit_reached');

    $recommendation = $user->advisorRecommendations()->sole();
    expect($recommendation->provider_calls)->toBe(1)
        ->and($recommendation->repair_attempts)->toBe(0);
});

test('an incomplete legacy profile fails before spending a provider call', function () {
    $user = User::factory()->pro()->withModules(Feature::Advisor)->create();
    $profile = advisorRecommendationProfile($user);
    $payload = $profile->profile_payload;
    unset($payload['constraints']['maximum_single_asset_allocation'], $payload['options_capability']);
    $profile->forceFill(['profile_payload' => $payload])->save();
    AdvisorRecommendationAgent::fake()->preventStrayPrompts();

    $this->actingAs($user)->postJson(route('advisor.recommendations.store'))
        ->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('failure_code', 'invalid_advisor_context');

    $recommendation = $user->advisorRecommendations()->sole();
    expect($recommendation->provider_calls)->toBe(0)
        ->and($recommendation->status)->toBe(AdvisorRecommendationStatus::Failed);
});

test('clarification is limited to three questions and one round', function () {
    $user = User::factory()->pro()->withModules(Feature::Advisor)->create();
    advisorRecommendationProfile($user);
    $clarification = [
        ...advisorValidRecommendation(),
        'status' => 'needs_clarification',
        'questions' => [['key' => 'identify', 'question' => 'Which market?', 'reason' => 'Ticker is ambiguous.', 'input_type' => 'text', 'options' => []]],
        'summary' => null, 'primary' => null, 'safer_alternative' => null, 'higher_risk_alternative' => null,
    ];
    AdvisorRecommendationAgent::fake([$clarification, advisorValidRecommendation()])->preventStrayPrompts();

    $id = $this->actingAs($user)->postJson(route('advisor.recommendations.store'))
        ->assertJsonPath('status', 'needs_clarification')->json('recommendation_id');

    $this->actingAs($user)->postJson(route('advisor.recommendations.clarify', $id), ['answers' => ['identify' => 'NASDAQ']])
        ->assertOk()->assertJsonPath('status', 'ready');

    expect($user->advisorRecommendations()->sole()->clarification_rounds)->toBe(1);
});

test('an oversized clarification request is rejected', function () {
    $user = User::factory()->pro()->withModules(Feature::Advisor)->create();
    advisorRecommendationProfile($user);
    $clarification = [
        ...advisorValidRecommendation(),
        'status' => 'needs_clarification',
        'questions' => collect(range(1, 4))->map(fn (int $number): array => [
            'key' => 'question_'.$number,
            'question' => 'Question '.$number,
            'reason' => 'Asset identity is incomplete.',
            'input_type' => 'text',
            'options' => [],
        ])->all(),
        'summary' => null,
        'primary' => null,
        'safer_alternative' => null,
        'higher_risk_alternative' => null,
    ];
    AdvisorRecommendationAgent::fake([$clarification])->preventStrayPrompts();

    $this->actingAs($user)->postJson(route('advisor.recommendations.store'))
        ->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('failure_code', 'invalid_clarification_request');
});

test('an AI suggested asset is usable only after explicit clarification consent', function () {
    $user = User::factory()->pro()->withModules(Feature::Advisor)->create();
    advisorRecommendationProfile($user);
    $clarification = [
        ...advisorValidRecommendation(),
        'status' => 'needs_clarification',
        'questions' => [[
            'key' => 'allow_diversifier',
            'question' => 'May I include a bond diversifier?',
            'reason' => 'It can reduce portfolio risk.',
            'input_type' => 'boolean',
            'options' => [],
        ]],
        'suggested_additional_assets' => [[
            'key' => 'accepted-bond',
            'name' => 'Bond diversifier',
            'category' => 'bond',
            'reason' => 'Diversification.',
        ]],
        'summary' => null,
        'primary' => null,
        'safer_alternative' => null,
        'higher_risk_alternative' => null,
    ];
    $final = advisorValidRecommendation();
    $final['primary']['allocations'] = [
        ['asset_key' => 'cash', 'target_percent' => 65, 'role' => 'Liquidity reserve', 'rationale' => 'Maintains resilience.'],
        ['asset_key' => 'bitcoin', 'target_percent' => 30, 'role' => 'Growth satellite', 'rationale' => 'Fits the risk envelope.'],
        ['asset_key' => 'accepted-bond', 'target_percent' => 5, 'role' => 'Diversifier', 'rationale' => 'Adds a defensive source of return.'],
    ];
    AdvisorRecommendationAgent::fake([$clarification, $final])->preventStrayPrompts();

    $id = $this->actingAs($user)->postJson(route('advisor.recommendations.store'))
        ->assertJsonPath('status', 'needs_clarification')
        ->json('recommendation_id');

    $this->actingAs($user)->postJson(route('advisor.recommendations.clarify', $id), [
        'answers' => ['allow_diversifier' => true],
        'accepted_assets' => [['asset_key' => 'accepted-bond', 'name' => 'Bond diversifier', 'category' => 'bond']],
    ])->assertOk()->assertJsonPath('status', 'ready');
});

test('model only agent instructions prohibit current market and live option claims', function () {
    $instructions = (string) app(AdvisorRecommendationAgent::class)->instructions();

    expect($instructions)->toContain("do not claim knowledge of today's prices")
        ->and($instructions)->toContain('never invent strikes, expirations, premiums, Greeks');
});

test('the validator rejects current market claims and exact option contracts in model only mode', function () {
    $user = User::factory()->pro()->create();
    $profile = advisorRecommendationProfile($user);
    $proposal = advisorValidRecommendation();
    $proposal['summary'] = "Today's market price and current market conditions favor this allocation.";
    $proposal['primary']['options_overlays'][0] = [
        'strategy' => 'protective_put',
        'underlying_asset_keys' => ['bitcoin'],
        'purpose' => 'Protection',
        'coverage_percent' => 50,
        'maximum_risk_budget_percent' => 1,
        'strike' => 50000,
        'conditions' => [],
        'benefits' => [],
        'tradeoffs' => [],
    ];
    $context = advisorContext($profile);
    $context['knowledge_mode'] = 'model_only';
    $codes = collect(app(AdvisorProposalValidator::class)->validate($proposal, $context))->pluck('code');

    expect($codes)->toContain('unsupported_current_market_claim')
        ->and($codes)->toContain('live_options_detail');
});

test('model only limitations may explicitly say that current data was not used', function () {
    $user = User::factory()->pro()->create();
    $profile = advisorRecommendationProfile($user);
    $proposal = advisorValidRecommendation();
    $proposal['uncertainties'] = ['Model-only mode provides no current prices, market conditions, or trading costs.'];
    $context = advisorContext($profile);
    $context['knowledge_mode'] = 'model_only';

    $codes = collect(app(AdvisorProposalValidator::class)->validate($proposal, $context))->pluck('code');

    expect($codes)->not->toContain('unsupported_current_market_claim');
});

test('removing a small speculative sleeve is a meaningfully safer alternative', function () {
    $user = User::factory()->pro()->create();
    $profile = advisorRecommendationProfile($user);
    $proposal = advisorValidRecommendation();
    $proposal['primary']['allocations'][0]['target_percent'] = 95;
    $proposal['primary']['allocations'][1]['target_percent'] = 5;
    $proposal['safer_alternative']['allocations'][0]['target_percent'] = 100;
    $proposal['safer_alternative']['allocations'][1]['target_percent'] = 0;

    $codes = collect(app(AdvisorProposalValidator::class)->validate($proposal, advisorContext($profile)))->pluck('code');

    expect($codes)->not->toContain('safer_not_meaningfully_safer');
});

test('the validator rejects unknown assets excessive risk and prohibited options', function () {
    $user = User::factory()->pro()->create();
    $profile = advisorRecommendationProfile($user);
    $proposal = advisorValidRecommendation();
    $proposal['primary']['allocations'][1]['asset_key'] = 'not-selected';
    $proposal['primary']['options_overlays'] = [[
        'strategy' => 'naked_short_call', 'underlying_asset_keys' => ['bitcoin'], 'purpose' => 'Income',
        'coverage_percent' => 10, 'maximum_risk_budget_percent' => 8, 'conditions' => [], 'benefits' => [], 'tradeoffs' => [],
    ]];

    $context = advisorContext($profile);
    $context['options_capability'] = [
        'willingness' => 'yes', 'broker_access' => true, 'allowed_underlying_categories' => ['crypto'],
        'allowed_strategy_families' => ['protective_put'], 'maximum_risk_budget_percent' => 5,
        'assignment_tolerance' => false, 'cap_upside' => false,
    ];
    $codes = collect(app(AdvisorProposalValidator::class)->validate($proposal, $context))->pluck('code');

    expect($codes)->toContain('unselected_asset')
        ->and($codes)->toContain('prohibited_options_strategy')
        ->and($codes)->toContain('options_risk_budget');
});

test('the validator treats an unmapped legacy risk band as unknown', function () {
    $user = User::factory()->pro()->create();
    $profile = advisorRecommendationProfile($user);
    $context = advisorContext($profile);
    $context['selected_assets'][1]['risk_band'] = 'legacy_conservative';

    $codes = collect(app(AdvisorProposalValidator::class)->validate(advisorValidRecommendation(), $context))->pluck('code');

    expect($codes)->toContain('unknown_risk_too_large');
});

test('missing context sections produce violations instead of runtime errors', function () {
    $user = User::factory()->pro()->create();
    $profile = advisorRecommendationProfile($user);
    $context = advisorContext($profile);
    unset($context['options_capability'], $context['constraints']['minimum_liquid_allocation']);

    $violations = app(AdvisorProposalValidator::class)->validate(advisorValidRecommendation(), $context);

    expect(collect($violations)->pluck('code'))->toContain('invalid_context')
        ->and(collect($violations)->pluck('path'))->toContain('options_capability', 'constraints.minimum_liquid_allocation');
});

test('recommendation generation deterministically uses the newest profile id when timestamps tie', function () {
    $user = User::factory()->pro()->withModules(Feature::Advisor)->create();
    $first = advisorRecommendationProfile($user);
    $second = advisorRecommendationProfile($user);
    $createdAt = now()->startOfSecond();
    $first->forceFill(['created_at' => $createdAt])->save();
    $second->forceFill(['created_at' => $createdAt])->save();
    AdvisorRecommendationAgent::fake([advisorValidRecommendation()])->preventStrayPrompts();

    $this->actingAs($user)->postJson(route('advisor.recommendations.store'))->assertOk();

    expect($user->advisorRecommendations()->sole()->advisor_profile_id)->toBe($second->id);
});

test('clarification provider calls have an independent daily rate limit', function () {
    $middleware = Route::getRoutes()
        ->getByName('advisor.recommendations.clarify')
        ?->gatherMiddleware() ?? [];

    expect($middleware)->toContain('throttle:advisor-clarifications');
});

test('recommendation throttling waits for a response and returns a friendly message', function () {
    $user = User::factory()->pro()->create();
    $request = Request::create('/advisor/recommendations', 'POST');
    $request->setUserResolver(fn (): User => $user);
    $limit = RateLimiter::limiter('advisor-recommendations')($request);

    expect($limit->afterCallback)->toBeCallable()
        ->and(($limit->afterCallback)(response()->noContent()))->toBeTrue()
        ->and(($limit->afterCallback)(response()->noContent(500)))->toBeFalse();

    $response = ($limit->responseCallback)($request, ['Retry-After' => 60]);

    expect($response->getStatusCode())->toBe(429)
        ->and($response->getData(true)['message'])->toBe(__('advisor.validation.recommendation_rate_limited'));
});

test('advisor rate limits are disabled in the local environment', function () {
    $originalEnvironment = app()->environment();
    app()->detectEnvironment(fn (): string => 'local');

    try {
        $request = Request::create('/advisor/recommendations', 'POST');

        expect(RateLimiter::limiter('advisor-recommendations')($request))->toBeInstanceOf(Unlimited::class)
            ->and(RateLimiter::limiter('advisor-clarifications')($request))->toBeInstanceOf(Unlimited::class)
            ->and(RateLimiter::limiter('advisor-consultations')($request))->toBeInstanceOf(Unlimited::class);
    } finally {
        app()->detectEnvironment(fn (): string => $originalEnvironment);
    }
});

test('cross user recommendation identifiers return not found', function () {
    $owner = User::factory()->pro()->withModules(Feature::Advisor)->create();
    $other = User::factory()->pro()->withModules(Feature::Advisor)->create();
    $profile = advisorRecommendationProfile($owner);
    $recommendation = $owner->advisorRecommendations()->create([
        'advisor_profile_id' => $profile->id, 'status' => 'ready', 'mode' => 'target_only', 'profile_version' => 1,
        'scoring_version' => 1, 'prompt_version' => 1, 'knowledge_version' => 1, 'context_hash' => hash('sha256', 'x'),
        'current_portfolio_included' => false, 'recommendation_payload' => advisorValidRecommendation(),
    ]);

    $this->actingAs($other)->get(route('advisor.recommendations.show', $recommendation))->assertNotFound();
});
