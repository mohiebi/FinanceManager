<?php

namespace App\Actions\Goals;

use App\Models\InvestmentAsset;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Support\Encryption\SealedField;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SaveGoal
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $vaultArmed = false): array
    {
        return [
            'investment_asset_id' => ['required', 'integer'],
            'title' => $vaultArmed
                ? SealedField::rules(required: false)
                : ['nullable', 'string', 'max:120'],
            // The server cannot check a number it cannot read — the honest cost
            // of the vault, and the same trade SaveInvestment makes.
            'target_quantity' => $vaultArmed
                ? SealedField::rules()
                : ['required', 'numeric', 'min:0.00000001'],
            'target_date' => ['required', 'date', 'after:today'],
            'started_on' => ['nullable', 'date', 'before_or_equal:today'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     *
     * @throws ValidationException when the asset is not one this user may use
     */
    public static function normalize(
        User $user,
        array $validated,
        bool $vaultArmed = false,
        bool $creating = true,
    ): array {
        $asset = InvestmentAsset::query()
            ->availableFor($user)
            ->whereKey($validated['investment_asset_id'])
            ->first();

        if (! $asset instanceof InvestmentAsset) {
            throw ValidationException::withMessages([
                'investment_asset_id' => __('validation.exists', ['attribute' => 'asset']),
            ]);
        }

        $validated['investment_asset_id'] = $asset->id;

        // Only on create, and only when the form omitted it: the baseline dates
        // the goal, so defaulting it on update would move an existing goal's
        // start to today and silently rewrite every pace figure it has.
        if ($creating) {
            $validated['started_on'] = $validated['started_on'] ?? Carbon::today()->toDateString();
        } elseif (! array_key_exists('started_on', $validated) || blank($validated['started_on'])) {
            unset($validated['started_on']);
        }

        return $vaultArmed
            ? SealedField::wrap($validated, ['title', 'target_quantity'])
            : $validated;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data, ?SavingsGoal $goal = null): SavingsGoal
    {
        if (! $goal instanceof SavingsGoal) {
            return $user->savingsGoals()->create($data);
        }

        $goal->fill($data);
        $goal->save();

        return $goal;
    }
}
