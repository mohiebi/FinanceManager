<?php

use App\Ai\Agents\AdvisorConsultationAgent;
use App\Ai\Agents\AdvisorRecommendationAgent;
use App\Enums\Feature;
use App\Models\User;
use App\Services\Advisor\AdvisorAIContextBuilder;

/**
 * The advisor writes prose, not just percentages, so the account's chosen
 * language has to travel with the request. It rides in the context as
 * response_language, which both agents are instructed to obey.
 */
beforeEach(fn () => $this->withoutVite());

test('the recommendation context names the account language', function () {
    $user = User::factory()->pro()->withModules(Feature::Advisor)->create(['locale' => 'fa']);
    $profile = advisorRecommendationProfile($user);

    $context = app(AdvisorAIContextBuilder::class)->build($user, $profile);

    expect($context['response_language'])->toBe('Persian (Farsi)');
});

test('an English account still asks for English', function () {
    $user = User::factory()->pro()->withModules(Feature::Advisor)->create();
    $profile = advisorRecommendationProfile($user);

    expect(app(AdvisorAIContextBuilder::class)->build($user, $profile)['response_language'])->toBe('English');
});

test('switching language invalidates the context hash so the answer is regenerated', function () {
    $user = User::factory()->pro()->withModules(Feature::Advisor)->create();
    $profile = advisorRecommendationProfile($user);
    $builder = app(AdvisorAIContextBuilder::class);

    $english = $builder->build($user, $profile);
    $user->forceFill(['locale' => 'de'])->save();
    $german = $builder->build($user->refresh(), $profile);

    expect($german)->not->toBe($english)
        ->and($german['response_language'])->toBe('German');
});

test('the prompt sent to the provider carries the language', function () {
    $user = User::factory()->pro()->withModules(Feature::Advisor)->create(['locale' => 'fa']);
    advisorRecommendationProfile($user);
    AdvisorRecommendationAgent::fake([advisorValidRecommendation()])->preventStrayPrompts();

    generateAdvisorRecommendation($user);

    AdvisorRecommendationAgent::assertPrompted(
        fn ($prompt): bool => str_contains($prompt->prompt, 'Persian (Farsi)')
    );
});

test('a consultation question carries the language too', function () {
    $user = User::factory()->pro()->withModules(Feature::Advisor)->create(['locale' => 'de']);
    [$recommendation] = advisorConsultationSetup($user);
    AdvisorConsultationAgent::fake([[
        'answer' => 'Bargeld sorgt für Liquidität.',
        'requires_recommendation_revision' => false,
        'suggested_question' => null,
    ]])->preventStrayPrompts();

    $this->actingAs($user)->postJson(route('advisor.recommendations.consult', $recommendation), [
        'message' => 'Warum ist Bargeld enthalten?',
    ])->assertOk();

    AdvisorConsultationAgent::assertPrompted(
        fn ($prompt): bool => str_contains($prompt->prompt, 'German')
    );
});

test('both agents are instructed to obey the requested language', function () {
    expect((new AdvisorRecommendationAgent)->instructions())->toContain('response_language')
        ->and((new AdvisorConsultationAgent)->instructions())->toContain('response_language');
});
