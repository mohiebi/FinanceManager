<?php

use App\Actions\Vault\ArmVault;
use App\Ai\Agents\AdvisorConsultationAgent;
use App\Enums\Feature;
use App\Models\AdvisorProfile;
use App\Models\AdvisorRecommendation;
use App\Models\InvestorAssessment;
use App\Models\User;
use App\Support\Encryption\UserCrypto;
use Illuminate\Support\Facades\DB;

/** @return array{0: AdvisorRecommendation, 1: AdvisorProfile} */
function advisorConsultationSetup(User $user): array
{
    $assessment = InvestorAssessment::factory()->for($user)->completed()->create();
    $profile = AdvisorProfile::factory()->for($user)->for($assessment, 'assessment')->create([
        'profile_payload' => [
            'persona' => 'balanced_investor',
            'scores' => ['effective_risk' => 50],
            'constraints' => ['maximum_single_asset_allocation' => 50],
            'selected_assets' => [['asset_key' => 'cash', 'name' => 'Cash']],
            'options_capability' => ['willingness' => 'no'],
        ],
    ]);
    $recommendation = AdvisorRecommendation::factory()->for($user)->for($profile, 'profile')->create([
        'recommendation_payload' => advisorValidRecommendation(),
    ]);

    return [$recommendation, $profile];
}

function armAdvisorConsultationVault(User $user): string
{
    $dek = app(ArmVault::class)->enroll($user);
    $kek = random_bytes(32);
    $aad = UserCrypto::aadFor('vault', 'dek');
    app(ArmVault::class)->arm($user, [
        'wrapped_passphrase' => UserCrypto::encrypt($dek, $kek, $aad),
        'wrapped_recovery' => UserCrypto::encrypt($dek, $kek, $aad),
        'kdf' => 'pbkdf2-sha256',
        'kdf_iterations' => 600000,
        'kdf_salt' => base64_encode(random_bytes(16)),
        'recovery_salt' => base64_encode(random_bytes(16)),
        'fingerprint' => hash('sha256', base64_decode($dek, true)),
    ]);
    $user->forgetFeatureSet();

    return $dek;
}

test('consultation history is encrypted and remains usable across multiple questions', function () {
    $user = User::factory()->pro()->withModules(Feature::Advisor)->create();
    [$recommendation] = advisorConsultationSetup($user);
    AdvisorConsultationAgent::fake([
        ['answer' => 'Cash provides liquidity.', 'requires_recommendation_revision' => false, 'suggested_question' => null],
        ['answer' => 'The speculative allocation is capped by your profile.', 'requires_recommendation_revision' => false, 'suggested_question' => null],
    ])->preventStrayPrompts();

    $this->actingAs($user)->postJson(route('advisor.recommendations.consult', $recommendation), [
        'message' => 'Why is cash included?',
    ])->assertOk()->assertJsonPath('payload.answer', 'Cash provides liquidity.');

    $this->actingAs($user)->postJson(route('advisor.recommendations.consult', $recommendation), [
        'message' => 'What limits the risky allocation?',
    ])->assertOk()->assertJsonPath('payload.requires_recommendation_revision', false);

    $messages = DB::table('advisor_messages')->where('advisor_recommendation_id', $recommendation->id)->get();
    expect($messages)->toHaveCount(4)
        ->and($messages->every(fn ($message): bool => UserCrypto::looksEncrypted($message->payload)))->toBeTrue()
        ->and($messages->pluck('payload')->implode(' '))->not->toContain('Why is cash included?');
});

test('vault consultation stores only browser sealed messages', function () {
    $user = User::factory()->pro()->withModules(Feature::Advisor)->create();
    [$recommendation] = advisorConsultationSetup($user);
    $dek = armAdvisorConsultationVault($user);
    AdvisorConsultationAgent::fake([[
        'answer' => 'This is an explanation only.',
        'requires_recommendation_revision' => false,
        'suggested_question' => null,
    ]])->preventStrayPrompts();
    $question = 'Explain the defensive allocation.';
    $sealedQuestion = UserCrypto::encrypt(
        json_encode(['content' => $question], JSON_THROW_ON_ERROR),
        base64_decode($dek, true),
        UserCrypto::aadFor('advisor_messages', 'payload'),
    );

    $response = $this->actingAs($user)->postJson(route('advisor.recommendations.consult', $recommendation), [
        'message' => $question,
        'recommendation_context' => advisorValidRecommendation(),
        'history' => [],
        'sealed_message' => $sealedQuestion,
    ])->assertOk()->assertJsonPath('vault_seal_required', true);

    expect(DB::table('advisor_messages')->where('advisor_recommendation_id', $recommendation->id)->sole()->payload)
        ->toBe($sealedQuestion)
        ->not->toContain($question);

    $sealedAnswer = UserCrypto::encrypt(
        json_encode($response->json('payload'), JSON_THROW_ON_ERROR),
        base64_decode($dek, true),
        UserCrypto::aadFor('advisor_messages', 'payload'),
    );
    $this->actingAs($user)->postJson(route('advisor.recommendations.messages.seal', $recommendation), [
        'payload' => $sealedAnswer,
        'role' => 'assistant',
    ])->assertOk();

    expect(DB::table('advisor_messages')->where('advisor_recommendation_id', $recommendation->id)->count())->toBe(2);
});

test('consultation cannot be read or written through another users recommendation id', function () {
    $owner = User::factory()->pro()->withModules(Feature::Advisor)->create();
    $other = User::factory()->pro()->withModules(Feature::Advisor)->create();
    [$recommendation] = advisorConsultationSetup($owner);

    $this->actingAs($other)->postJson(route('advisor.recommendations.consult', $recommendation), [
        'message' => 'Explain this.',
    ])->assertNotFound();
});
