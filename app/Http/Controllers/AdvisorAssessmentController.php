<?php

namespace App\Http\Controllers;

use App\Enums\InvestorAssessmentStatus;
use App\Http\Requests\Advisor\CompleteAssessmentRequest;
use App\Http\Requests\Advisor\UpdateAssessmentSectionRequest;
use App\Models\InvestmentAsset;
use App\Models\InvestorAssessment;
use App\Services\Advisor\AdvisorAssessmentDefinition;
use App\Services\Advisor\AdvisorProfileBuilder;
use App\Support\Encryption\EncryptedValue;
use App\Support\Encryption\SealedField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdvisorAssessmentController extends Controller
{
    public function show(Request $request, InvestorAssessment $assessment, AdvisorAssessmentDefinition $definition): Response
    {
        $section = max(1, min(8, (int) $request->integer('section', min(8, $assessment->last_completed_section + 1))));

        return Inertia::render('Advisor/Assessment', [
            'assessment' => [
                ...$assessment->only(['id', 'assessment_version', 'scoring_version', 'last_completed_section']),
                'status' => $assessment->status->value,
            ],
            'currentSection' => $section,
            'definition' => $definition->all(),
            'answers' => $assessment->answers()->get()->mapWithKeys(fn ($answer): array => [$answer->question_key => $answer->answer]),
            'supportedAssets' => InvestmentAsset::query()->availableFor($request->user())->orderByDesc('is_default')->orderBy('name')->get()->map(fn (InvestmentAsset $asset): array => [
                'id' => $asset->id,
                'name' => $asset->label(),
                'slug' => $asset->slug,
                'unit' => $asset->unit,
                'icon' => $asset->icon,
                'color' => $asset->color,
                ...$this->classifyAsset($asset->slug),
            ]),
            'vaultArmed' => $request->user()->vaultIsArmed(),
        ]);
    }

    public function updateSection(UpdateAssessmentSectionRequest $request, InvestorAssessment $assessment, int $section, AdvisorAssessmentDefinition $definition): RedirectResponse
    {
        abort_if($assessment->isCompleted(), 409, __('advisor.validation.completed_immutable'));
        if ($section < 1 || $section > 8) {
            throw ValidationException::withMessages(['section' => __('advisor.validation.invalid_section')]);
        }

        $answers = $request->validated('answers');
        $expectedKeys = $definition->section($section)['questions'];
        $isPartial = $request->boolean('partial');

        if ($request->user()->vaultIsArmed()) {
            $sealedRules = array_fill_keys($expectedKeys, SealedField::rules());
            $answers = $isPartial
                // A draft only carries what has been answered so far, so only
                // the keys actually present are validated and kept.
                ? array_intersect_key($answers, $sealedRules)
                : $answers;
            Validator::make($answers, $isPartial ? array_intersect_key($sealedRules, $answers) : $sealedRules)->validate();
            $answers = collect($answers)->map(fn (string $ciphertext): EncryptedValue => new EncryptedValue($ciphertext, 'answer'))->all();
        } else {
            $answers = $isPartial
                ? $definition->validatePartialSection($section, $answers)
                : $definition->validateSection($section, $answers);
        }

        DB::transaction(function () use ($assessment, $answers, $section, $isPartial): void {
            foreach ($answers as $questionKey => $answer) {
                $assessment->answers()->updateOrCreate(
                    ['question_key' => $questionKey],
                    ['user_id' => $assessment->user_id, 'answer' => $answer],
                );
            }

            // A draft has not finished the section, so it must not advance the
            // resume marker — only a complete section does that.
            if (! $isPartial) {
                $assessment->forceFill(['last_completed_section' => max($assessment->last_completed_section, $section)])->save();
            }
        });

        $nextSection = $isPartial ? max(1, $section - 1) : min(8, $section + 1);

        return redirect()->route('advisor.assessments.show', ['assessment' => $assessment, 'section' => $nextSection]);
    }

    public function complete(CompleteAssessmentRequest $request, InvestorAssessment $assessment, AdvisorAssessmentDefinition $definition, AdvisorProfileBuilder $profileBuilder): RedirectResponse
    {
        abort_if($assessment->isCompleted(), 409, __('advisor.validation.completed_immutable'));
        $answers = $assessment->answers()->get()->mapWithKeys(fn ($answer): array => [$answer->question_key => $answer->answer])->all();
        if (array_diff($definition->requiredQuestionKeys(), array_keys($answers)) !== []) {
            throw ValidationException::withMessages(['assessment' => __('advisor.validation.missing_answers')]);
        }

        $vaultArmed = $request->user()->vaultIsArmed();
        $profilePayload = $vaultArmed
            ? $profileBuilder->normalizeDerivedProfile(
                $this->validateClientProfile((array) $request->validated('derived_profile')),
                $request->user(),
            )
            : $profileBuilder->build($answers, $request->user());

        DB::transaction(function () use ($assessment, $request, $profilePayload, $vaultArmed): void {
            $assessment->forceFill([
                'status' => InvestorAssessmentStatus::Completed,
                'last_completed_section' => 8,
                'scoring_origin' => $vaultArmed ? 'browser' : 'server',
                'completed_at' => now(),
            ])->save();
            $assessment->profile()->create([
                'user_id' => $assessment->user_id,
                'profile_version' => 1,
                'profile_payload' => $profilePayload,
                'ai_consent_at' => $request->boolean('ai_consent') ? now() : null,
            ]);
        });

        return redirect()->route('advisor.profile');
    }

    /** @return array<string, mixed> */
    private function classifyAsset(string $slug): array
    {
        return match ($slug) {
            'gold', 'silver' => ['category' => 'metal', 'risk_band' => 'moderate', 'liquidity' => 'same_day', 'currency' => 'USD'],
            'bitcoin', 'ethereum' => ['category' => 'crypto', 'risk_band' => 'speculative', 'liquidity' => 'same_day', 'currency' => 'USD'],
            'usd', 'eur' => ['category' => 'currency', 'risk_band' => 'defensive', 'liquidity' => 'same_day', 'currency' => strtoupper($slug)],
            default => ['category' => 'other', 'risk_band' => 'unknown', 'liquidity' => 'within_week', 'currency' => 'USD'],
        };
    }

    /** @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    private function validateClientProfile(array $profile): array
    {
        Validator::make($profile, [
            'profile_version' => ['required', 'integer', 'in:1'],
            'scores' => ['required', 'array:risk_willingness,risk_capacity,financial_resilience,liquidity_need,investment_knowledge,behavioral_stability,loss_aversion,return_ambition,time_horizon,raw_risk,capacity_ceiling,willingness_ceiling,effective_risk'],
            'scores.risk_willingness' => ['required', 'integer', 'between:0,100'],
            'scores.risk_capacity' => ['required', 'integer', 'between:0,100'],
            'scores.financial_resilience' => ['required', 'integer', 'between:0,100'],
            'scores.liquidity_need' => ['required', 'integer', 'between:0,100'],
            'scores.investment_knowledge' => ['required', 'integer', 'between:0,100'],
            'scores.behavioral_stability' => ['required', 'integer', 'between:0,100'],
            'scores.loss_aversion' => ['required', 'integer', 'between:0,100'],
            'scores.return_ambition' => ['required', 'integer', 'between:0,100'],
            'scores.time_horizon' => ['required', 'integer', 'between:0,100'],
            'maximum_tolerated_drawdown' => ['required', 'integer', 'between:5,50'],
            'financial_context' => ['required', 'array:income_stability,emergency_fund,high_interest_debt,portfolio_share_of_liquid_wealth'],
            'financial_context.income_stability' => ['required', 'in:very_stable,mostly_stable,variable,unpredictable,no_regular_income'],
            'financial_context.emergency_fund' => ['required', 'in:under_1,1_3,3_6,6_12,over_12'],
            'financial_context.high_interest_debt' => ['required', 'in:none,manageable,significant'],
            'financial_context.portfolio_share_of_liquid_wealth' => ['required', 'in:under_10,10_25,25_50,50_75,over_75'],
            'loss_context' => ['required', 'array:permanent_loss_impact,drawdown_10_response,drawdown_20_response,drawdown_35_response'],
            'loss_context.permanent_loss_impact' => ['required', 'in:severe,significant,moderate,small,minimal'],
            'loss_context.drawdown_10_response' => ['required', 'in:sell_all,sell_part,hold,buy_some,buy_more'],
            'loss_context.drawdown_20_response' => ['required', 'in:sell_all,sell_part,hold,buy_some,buy_more'],
            'loss_context.drawdown_35_response' => ['required', 'in:cannot_tolerate,extremely_uncomfortable,uncomfortable_hold,normal,buying_opportunity'],
            'goals' => ['required', 'array:primary,importance,time_horizon,early_withdrawal_likelihood,liquidity,target_return'],
            'goals.primary' => ['required', 'string', 'max:40'],
            'goals.importance' => ['required', 'in:optional,important,very_important,essential'],
            'goals.time_horizon' => ['required', 'in:under_1_year,1_3_years,3_5_years,5_10_years,10_plus,no_planned_withdrawal'],
            'goals.early_withdrawal_likelihood' => ['required', 'in:very_unlikely,unlikely,possible,likely,very_likely'],
            'goals.liquidity.proportion' => ['required', 'in:almost_none,under_10,10_25,25_50,over_50'],
            'goals.liquidity.speed' => ['required', 'in:same_day,within_week,within_month,several_months'],
            'goals.target_return' => ['required', 'in:purchasing_power,5_8,8_12,12_18,18_plus,not_sure'],
            'portfolio_preferences' => ['required', 'array:scope,primary_currency,country,markets,tax_sensitive'],
            'portfolio_preferences.scope' => ['required', 'in:current,new,both'],
            'portfolio_preferences.primary_currency' => ['required', 'string', 'max:12'],
            'portfolio_preferences.country' => ['required', 'string', 'max:80'],
            'portfolio_preferences.markets' => ['required', 'array', 'min:1', 'max:20'],
            'portfolio_preferences.markets.*' => ['string', 'max:80'],
            'portfolio_preferences.tax_sensitive' => ['required', 'boolean'],
            'selected_assets' => ['required', 'array', 'min:1', 'max:30'],
            'selected_assets.*.asset_key' => ['required', 'string', 'max:80', 'distinct'],
            'selected_assets.*.source' => ['required', 'in:cashpilot,custom'],
            'selected_assets.*.investment_asset_id' => ['nullable', 'integer'],
            'selected_assets.*.name' => ['required', 'string', 'max:120'],
            'selected_assets.*.ticker' => ['nullable', 'string', 'max:30'],
            'selected_assets.*.identifier' => ['nullable', 'string', 'max:80'],
            'selected_assets.*.exchange_or_market' => ['nullable', 'string', 'max:80'],
            'selected_assets.*.country' => ['nullable', 'string', 'max:80'],
            'selected_assets.*.currency' => ['required', 'string', 'max:12'],
            'selected_assets.*.category' => ['required', 'in:stock,etf,bond,currency,metal,crypto,commodity,real_estate,private_asset,other'],
            'selected_assets.*.risk_band' => ['required', 'in:defensive,moderate,growth,speculative,unknown'],
            'selected_assets.*.liquidity' => ['required', 'in:same_day,within_week,within_month,illiquid'],
            'selected_assets.*.perspective' => ['required', 'in:bearish,neutral,bullish'],
            'selected_assets.*.conviction' => ['required', 'in:low,medium,high'],
            'selected_assets.*.holding_period' => ['required', 'in:under_1_year,1_3_years,3_5_years,5_10_years,10_plus'],
            'selected_assets.*.inclusion' => ['required', 'in:required,allowed'],
            'options_capability' => ['required', 'array'],
            'constraints.maximum_single_asset_allocation' => ['required', 'integer', 'between:5,100'],
            'warnings' => ['present', 'array'],
        ])->validate();

        return $profile;
    }
}
