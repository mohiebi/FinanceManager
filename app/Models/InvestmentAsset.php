<?php

namespace App\Models;

use App\Enums\AssetClass;
use App\Enums\InvestmentAssetPriceSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'name',
    'slug',
    'unit',
    'asset_class',
    'underlying_asset_id',
    'underlying_ratio',
    'icon',
    'icon_svg',
    'color',
    'is_default',
    'price_source_type',
    'price_source_config',
])]
class InvestmentAsset extends Model
{
    protected static function booted(): void
    {
        static::saving(function (InvestmentAsset $asset): void {
            if (! $asset->slug) {
                $asset->slug = self::slugForName($asset->name);
            }

            $asset->is_default = $asset->user_id === null;
            $asset->normaliseUnderlying();
        });
    }

    public static function slugForName(string $name): string
    {
        $slug = Str::slug($name);

        if ($slug !== '') {
            return $slug;
        }

        return 'asset-'.substr(sha1(mb_strtolower(trim($name))), 0, 16);
    }

    public function label(): string
    {
        if (! $this->is_default) {
            return $this->name;
        }

        $translationKey = "finance.assets.{$this->slug}";
        $translatedLabel = __($translationKey);

        return $translatedLabel === $translationKey ? $this->name : $translatedLabel;
    }

    /**
     * @return BelongsTo<User, InvestmentAsset>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Investment, InvestmentAsset>
     */
    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    /**
     * The market this asset is really a bet on.
     *
     * Null on an asset that is its own market — Gold, Silver, the dollar. Those
     * are the roots, and the nesting stops there: see {@see self::canBeUnderlying()}.
     *
     * @return BelongsTo<InvestmentAsset, InvestmentAsset>
     */
    public function underlying(): BelongsTo
    {
        return $this->belongsTo(self::class, 'underlying_asset_id');
    }

    /**
     * The assets that track this one — half coins under gold, bars under silver.
     *
     * @return HasMany<InvestmentAsset, InvestmentAsset>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(self::class, 'underlying_asset_id');
    }

    /**
     * Whether this asset may be named as another's underlying.
     *
     * Only roots qualify, which caps the tree at one level. That is not a
     * limitation worth lifting: it removes cycle detection, recursive queries and
     * the question of what a three-deep chain would even mean, and one level is
     * enough to say a half coin is gold.
     */
    public function canBeUnderlying(): bool
    {
        return $this->underlying_asset_id === null;
    }

    /**
     * Keeps the exposure tree one level deep, whatever it was handed.
     *
     * An asset pointed at another asset that already tracks something is
     * hoisted to that root rather than rejected — if a 250g silver bar is
     * declared to track a 100g silver bar, the honest answer is that it tracks
     * silver. A self-reference is simply dropped.
     *
     * The form request refuses these cases outright so the user gets a message;
     * this is the backstop for every other writer, the MCP proposal path
     * included.
     */
    protected function normaliseUnderlying(): void
    {
        if ($this->underlying_asset_id === null) {
            return;
        }

        if ($this->exists && (int) $this->underlying_asset_id === (int) $this->id) {
            $this->underlying_asset_id = null;
            $this->underlying_ratio = null;

            return;
        }

        $parent = self::query()->find($this->underlying_asset_id);

        if ($parent === null) {
            $this->underlying_asset_id = null;
            $this->underlying_ratio = null;

            return;
        }

        if ($parent->underlying_asset_id !== null) {
            $this->underlying_asset_id = $parent->underlying_asset_id;
            // The ratio described a hop that no longer exists, and chaining two
            // of them would be a fabricated number.
            $this->underlying_ratio = null;
        }
    }

    /**
     * The id everything about this asset should be aggregated under.
     *
     * The whole point of the column: this is what turns four rows into
     * "gold: 62%, silver: 12%".
     */
    public function exposureId(): int
    {
        return $this->underlying_asset_id ?? $this->id;
    }

    #[Scope]
    protected function availableFor(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user): void {
            $query->whereNull('user_id')
                ->orWhere('user_id', $user->id);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'asset_class' => AssetClass::class,
            'underlying_ratio' => 'float',
            'price_source_type' => InvestmentAssetPriceSource::class,
            'price_source_config' => 'array',
        ];
    }
}
