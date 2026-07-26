<?php

namespace App\Actions\Investments;

use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\User;
use App\Support\Encryption\EncryptedValue;
use App\Support\Encryption\UserCrypto;
use Closure;
use Illuminate\Validation\ValidationException;

/**
 * Shared investment-entry validation and persistence used by the web
 * InvestmentController and the MCP tools so both surfaces resolve assets and
 * derive cost basis identically.
 */
class SaveInvestment
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $vaultArmed = false): array
    {
        return [
            'investment_asset_id' => ['nullable', 'integer'],
            'asset_type' => ['nullable', 'string', 'max:100'],
            'quantity' => $vaultArmed
                ? self::encryptedRules()
                : ['required', 'numeric', 'min:0.00000001'],
            'total_cost' => $vaultArmed
                ? ['nullable']
                : ['nullable', 'numeric', 'min:0'],
            'cost_basis' => $vaultArmed
                ? self::encryptedRules(required: false)
                : ['nullable', 'numeric', 'min:0'],
            'cost_basis_currency' => ['nullable', 'string', 'max:10'],
            'note' => $vaultArmed
                ? self::encryptedRules(required: false)
                : ['nullable', 'string', 'max:500'],
            'occurred_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * Resolves the asset (by id, falling back to slug) among those available
     * to the user, then derives cost basis from total_cost when provided.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     *
     * @throws ValidationException when no matching asset is available
     */
    public static function normalize(User $user, array $validated, bool $vaultArmed = false): array
    {
        $asset = self::resolveAsset($user, $validated);

        if ($vaultArmed) {
            unset($validated['total_cost']);

            $validated['investment_asset_id'] = $asset->id;
            $validated['asset_type'] = $asset->slug;
            $validated['cost_basis_currency'] = filled($validated['cost_basis'] ?? null)
                ? ($validated['cost_basis_currency'] ?? null)
                : null;

            foreach (['quantity', 'cost_basis', 'note'] as $field) {
                if (filled($validated[$field] ?? null)) {
                    $validated[$field] = new EncryptedValue($validated[$field], $field);
                }
            }

            return $validated;
        }

        $quantity = (float) $validated['quantity'];
        $totalCost = self::nullableFloat($validated['total_cost'] ?? null);
        $costBasis = $totalCost !== null
            ? $totalCost / $quantity
            : self::nullableFloat($validated['cost_basis'] ?? null);

        unset($validated['total_cost']);

        $validated['investment_asset_id'] = $asset->id;
        $validated['asset_type'] = $asset->slug;
        $validated['cost_basis'] = $costBasis;
        $validated['cost_basis_currency'] = $costBasis !== null
            ? ($validated['cost_basis_currency'] ?? null)
            : null;

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $data  normalized investment attributes
     */
    public function create(User $user, array $data): Investment
    {
        return $user->investments()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data  normalized investment attributes
     */
    public function update(Investment $investment, array $data): Investment
    {
        $investment->fill($data)->save();

        return $investment;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function resolveAsset(User $user, array $input): InvestmentAsset
    {
        $query = InvestmentAsset::query()->availableFor($user);

        $asset = null;
        $assetId = $input['investment_asset_id'] ?? null;
        if ($assetId !== null && $assetId !== '') {
            $asset = (clone $query)->whereKey((int) $assetId)->first();
        }

        $assetType = $input['asset_type'] ?? null;
        if (! $asset && $assetType !== null && $assetType !== '') {
            $asset = (clone $query)->where('slug', (string) $assetType)->first();
        }

        if (! $asset) {
            throw ValidationException::withMessages([
                'investment_asset_id' => __('settings.assets.invalid'),
            ]);
        }

        return $asset;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * @return array<int, mixed>
     */
    private static function encryptedRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:8192',
            function (string $attribute, mixed $value, Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                if (! UserCrypto::looksEncrypted(is_string($value) ? $value : null)) {
                    $fail('The :attribute must be encrypted by your browser before it is sent.');
                }
            },
        ];
    }
}
