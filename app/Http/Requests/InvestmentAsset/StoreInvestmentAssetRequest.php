<?php

namespace App\Http\Requests\InvestmentAsset;

use App\Enums\AssetClass;
use App\Enums\InvestmentAssetPriceSource;
use App\Models\InvestmentAsset;
use App\Support\SvgIconSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInvestmentAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:20'],
            'asset_class' => ['nullable', Rule::enum(AssetClass::class)],
            'underlying_asset_id' => ['nullable', 'integer'],
            'underlying_ratio' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'icon' => ['nullable', 'string', 'max:20'],
            'icon_svg' => ['nullable', 'string', 'max:5000'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'price_source_type' => ['required', Rule::in([
                InvestmentAssetPriceSource::Manual->value,
                InvestmentAssetPriceSource::Formula->value,
                InvestmentAssetPriceSource::Json->value,
                InvestmentAssetPriceSource::Xml->value,
            ])],
            'price_source_config' => ['nullable', 'array'],
            'price_source_config.price' => ['nullable', 'numeric', 'min:0'],
            'price_source_config.formula' => ['nullable', 'string', 'max:500'],
            'price_source_config.url' => ['nullable', 'url', 'max:2048'],
            'price_source_config.path' => ['nullable', 'string', 'max:255'],
            'price_source_config.xpath' => ['nullable', 'string', 'max:500'],
            'price_source_config.divide_by' => ['nullable', 'numeric', 'min:0.00000001'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'unit' => trim((string) $this->input('unit')),
            'icon' => trim((string) $this->input('icon')),
            'color' => trim((string) $this->input('color')) ?: '#02CD86',
        ]);

        // An empty select posts as '', which is not a valid enum value and is not
        // null either — normalise both to "not stated".
        foreach (['asset_class', 'underlying_asset_id', 'underlying_ratio'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateUniqueSlug($validator);
                $this->validateSvgIcon($validator);
                $this->validatePriceSource($validator);
                $this->validateUnderlying($validator);
            },
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     slug: string,
     *     unit: string,
     *     asset_class: string|null,
     *     underlying_asset_id: int|null,
     *     underlying_ratio: float|null,
     *     icon: string|null,
     *     icon_svg: string|null,
     *     color: string,
     *     price_source_type: string,
     *     price_source_config: array<string, mixed>
     * }
     */
    public function assetData(): array
    {
        $name = (string) $this->validated('name');
        $sourceType = (string) $this->validated('price_source_type');
        $underlyingId = $this->validated('underlying_asset_id');

        return [
            'name' => $name,
            'slug' => InvestmentAsset::slugForName($name),
            'unit' => (string) $this->validated('unit'),
            'asset_class' => $this->nullableString($this->validated('asset_class')),
            'underlying_asset_id' => blank($underlyingId) ? null : (int) $underlyingId,
            // Meaningless without something to be a ratio of, and leaving a stale
            // one behind would make a later equivalent-quantity total wrong.
            'underlying_ratio' => blank($underlyingId)
                ? null
                : $this->nullableFloat($this->validated('underlying_ratio')),
            'icon' => $this->nullableString($this->validated('icon')),
            'icon_svg' => app(SvgIconSanitizer::class)->sanitize($this->validated('icon_svg')),
            'color' => (string) ($this->validated('color') ?: '#02CD86'),
            'price_source_type' => $sourceType,
            'price_source_config' => $this->sourceConfig($sourceType),
        ];
    }

    /**
     * The underlying has to be an asset the user can see, and a root.
     *
     * Rejecting a non-root here rather than silently flattening it (which is
     * what the model does for every other writer) is the difference between the
     * user learning that the tree is one level deep and quietly getting a
     * different answer than they asked for.
     */
    protected function validateUnderlying(Validator $validator): void
    {
        $underlyingId = $this->input('underlying_asset_id');

        if (blank($underlyingId)) {
            return;
        }

        $underlying = InvestmentAsset::query()
            ->availableFor($this->user())
            ->whereKey((int) $underlyingId)
            ->first();

        if ($underlying === null) {
            $validator->errors()->add('underlying_asset_id', __('settings.assets.invalid'));

            return;
        }

        $editing = $this->route('investment_asset');

        if ($editing instanceof InvestmentAsset && $underlying->id === $editing->id) {
            $validator->errors()->add('underlying_asset_id', __('settings.assets.underlying_self'));

            return;
        }

        if (! $underlying->canBeUnderlying()) {
            $validator->errors()->add('underlying_asset_id', __('settings.assets.underlying_not_root'));

            return;
        }

        if ($editing instanceof InvestmentAsset && $editing->variants()->exists()) {
            $validator->errors()->add('underlying_asset_id', __('settings.assets.underlying_has_variants'));
        }
    }

    protected function validateUniqueSlug(Validator $validator): void
    {
        $name = (string) $this->input('name');

        if ($name === '') {
            return;
        }

        $exists = InvestmentAsset::query()
            ->availableFor($this->user())
            ->where('slug', InvestmentAsset::slugForName($name))
            ->exists();

        if ($exists) {
            $validator->errors()->add('name', __('settings.assets.already_exists'));
        }
    }

    protected function validatePriceSource(Validator $validator): void
    {
        $sourceType = (string) $this->input('price_source_type');
        $config = $this->input('price_source_config', []);

        if (! is_array($config)) {
            return;
        }

        match ($sourceType) {
            InvestmentAssetPriceSource::Manual->value => $this->requireConfigValue($validator, $config, 'price'),
            InvestmentAssetPriceSource::Formula->value => $this->requireConfigValue($validator, $config, 'formula'),
            InvestmentAssetPriceSource::Json->value => $this->requireConfigValues($validator, $config, ['url', 'path']),
            InvestmentAssetPriceSource::Xml->value => $this->requireConfigValues($validator, $config, ['url', 'xpath']),
            default => null,
        };
    }

    protected function validateSvgIcon(Validator $validator): void
    {
        $svg = $this->nullableString($this->input('icon_svg'));

        if ($svg === null) {
            return;
        }

        if (app(SvgIconSanitizer::class)->sanitize($svg) === null) {
            $validator->errors()->add('icon_svg', __('settings.assets.invalid_svg'));
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function requireConfigValue(Validator $validator, array $config, string $key): void
    {
        if (blank($config[$key] ?? null)) {
            $validator->errors()->add("price_source_config.{$key}", __('settings.assets.source_required'));
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $keys
     */
    protected function requireConfigValues(Validator $validator, array $config, array $keys): void
    {
        foreach ($keys as $key) {
            $this->requireConfigValue($validator, $config, $key);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function sourceConfig(string $sourceType): array
    {
        $config = $this->validated('price_source_config') ?? [];

        return match ($sourceType) {
            InvestmentAssetPriceSource::Manual->value => [
                'price' => (float) ($config['price'] ?? 0),
            ],
            InvestmentAssetPriceSource::Formula->value => [
                'formula' => trim((string) ($config['formula'] ?? '')),
            ],
            InvestmentAssetPriceSource::Json->value => [
                'url' => trim((string) ($config['url'] ?? '')),
                'path' => trim((string) ($config['path'] ?? '')),
                'divide_by' => $this->nullableFloat($config['divide_by'] ?? null),
                'formula' => trim((string) ($config['formula'] ?? '')),
            ],
            InvestmentAssetPriceSource::Xml->value => [
                'url' => trim((string) ($config['url'] ?? '')),
                'xpath' => trim((string) ($config['xpath'] ?? '')),
                'divide_by' => $this->nullableFloat($config['divide_by'] ?? null),
                'formula' => trim((string) ($config['formula'] ?? '')),
            ],
            default => [],
        };
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
