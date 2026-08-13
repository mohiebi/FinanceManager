<?php

namespace App\Services\Advisor;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdvisorAssessmentDefinition
{
    /** @var array<string, mixed>|null */
    private ?array $definition = null;

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->definition !== null) {
            return $this->definition;
        }

        $contents = file_get_contents(resource_path('js/lib/advisor/scoring-v1.json'));

        return $this->definition = json_decode((string) $contents, true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    public function section(int $number): array
    {
        foreach ($this->all()['sections'] as $section) {
            if ($section['number'] === $number) {
                return $section;
            }
        }

        throw ValidationException::withMessages(['section' => __('advisor.validation.invalid_section')]);
    }

    /** @return array<int, string> */
    public function requiredQuestionKeys(): array
    {
        return array_values(Arr::flatten(array_column($this->all()['sections'], 'questions')));
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function validateSection(int $number, array $answers): array
    {
        $section = $this->section($number);
        $rules = [];

        foreach ($section['questions'] as $questionKey) {
            $question = $this->all()['questions'][$questionKey];
            $rules[$questionKey] = match ($question['input']) {
                'multi' => ['required', 'array', 'min:1'],
                'liquidity', 'portfolio_preferences', 'options_capability' => ['required', 'array'],
                default => ['required', 'string', 'in:'.implode(',', $question['options'])],
            };

            if ($question['input'] === 'multi') {
                $rules["{$questionKey}.*"] = ['string', 'distinct', 'in:'.implode(',', $question['options'])];
            }
        }

        $validated = Validator::make($answers, $rules)->validate();

        if ($number === 2) {
            $this->validateLiquidity($validated['q10_liquidity']);
        }

        if ($number === 7) {
            $this->validatePortfolioPreferences($validated['portfolio_preferences']);
        }

        if ($number === 8) {
            $this->validateOptionsCapability($validated['options_capability']);
        }

        return $validated;
    }

    /** @param array<string, mixed> $value */
    private function validateLiquidity(array $value): void
    {
        Validator::make($value, [
            'proportion' => ['required', 'in:almost_none,under_10,10_25,25_50,over_50'],
            'speed' => ['required', 'in:same_day,within_week,within_month,several_months'],
        ])->validate();
    }

    /** @param array<string, mixed> $value */
    private function validatePortfolioPreferences(array $value): void
    {
        $validator = Validator::make($value, [
            'scope' => ['required', 'in:current,new,both'],
            'new_investable_amount' => ['nullable', 'numeric', 'min:0'],
            'recurring_contribution' => ['nullable', 'numeric', 'min:0'],
            'primary_currency' => ['required', 'string', 'max:12'],
            'country' => ['required', 'string', 'max:80'],
            'markets' => ['required', 'array', 'min:1', 'max:20'],
            'markets.*' => ['string', 'max:80', 'distinct'],
            'maximum_single_asset_allocation' => ['required', 'integer', 'between:5,100'],
            'tax_sensitive' => ['required', 'boolean'],
            'exclusions' => ['present', 'array', 'max:20'],
            'exclusions.*' => ['string', 'max:80', 'distinct'],
            'assets' => ['required', 'array', 'min:1', 'max:30'],
            'assets.*.asset_key' => ['required', 'string', 'max:80', 'distinct'],
            'assets.*.source' => ['required', 'in:cashpilot,custom'],
            'assets.*.investment_asset_id' => ['nullable', 'integer'],
            'assets.*.name' => ['required', 'string', 'max:120'],
            'assets.*.ticker' => ['nullable', 'string', 'max:30'],
            'assets.*.identifier' => ['nullable', 'string', 'max:80'],
            'assets.*.exchange_or_market' => ['nullable', 'string', 'max:80'],
            'assets.*.country' => ['nullable', 'string', 'max:80'],
            'assets.*.currency' => ['required', 'string', 'max:12'],
            'assets.*.category' => ['required', 'in:stock,etf,bond,currency,metal,crypto,commodity,real_estate,private_asset,other'],
            'assets.*.risk_band' => ['required', 'in:defensive,moderate,growth,speculative,unknown'],
            'assets.*.liquidity' => ['required', 'in:same_day,within_week,within_month,illiquid'],
            'assets.*.perspective' => ['required', 'in:bearish,neutral,bullish'],
            'assets.*.conviction' => ['required', 'in:low,medium,high'],
            'assets.*.holding_period' => ['required', 'in:under_1_year,1_3_years,3_5_years,5_10_years,10_plus'],
            'assets.*.inclusion' => ['required', 'in:required,allowed'],
            'assets.*.notes' => ['nullable', 'string', 'max:300'],
        ]);

        $validator->after(function ($validator) use ($value): void {
            foreach ($value['assets'] ?? [] as $index => $asset) {
                if (($asset['source'] ?? null) === 'custom'
                    && blank($asset['ticker'] ?? null)
                    && blank($asset['identifier'] ?? null)) {
                    $validator->errors()->add("assets.{$index}.ticker", __('advisor.validation.custom_asset_identifier'));
                }
            }
        });

        $validator->validate();
    }

    /** @param array<string, mixed> $value */
    private function validateOptionsCapability(array $value): void
    {
        $willingness = $value['willingness'] ?? null;
        Validator::make($value, [
            'willingness' => ['required', 'in:no,yes,not_sure'],
            'broker_access' => [$willingness === 'no' ? 'nullable' : 'required', 'boolean'],
            'approved_underlyings' => [$willingness === 'no' ? 'nullable' : 'present', 'array'],
            'approved_underlyings.*' => ['in:stocks,etfs,indices,commodities,currencies,crypto'],
            'experience_years' => [$willingness === 'no' ? 'nullable' : 'required', 'in:none,under_1,1_3,3_plus'],
            'trade_count' => [$willingness === 'no' ? 'nullable' : 'required', 'in:none,1_10,11_50,50_plus'],
            'strategies_used' => ['nullable', 'array'],
            'knowledge_answers' => [$willingness === 'no' ? 'nullable' : 'required', 'array', 'size:3'],
            'knowledge_answers.*' => ['boolean'],
            'objective' => [$willingness === 'no' ? 'nullable' : 'required', 'in:downside_hedging,income,defined_risk_growth,combination'],
            'maximum_risk_budget_percent' => [$willingness === 'no' ? 'nullable' : 'required', 'numeric', 'between:0,10'],
            'recurring_premium' => [$willingness === 'no' ? 'nullable' : 'required', 'boolean'],
            'cap_upside' => [$willingness === 'no' ? 'nullable' : 'required', 'boolean'],
            'assignment_tolerance' => [$willingness === 'no' ? 'nullable' : 'required', 'boolean'],
            'monitoring' => [$willingness === 'no' ? 'nullable' : 'required', 'in:daily,weekly,monthly,rarely'],
        ])->validate();
    }
}
