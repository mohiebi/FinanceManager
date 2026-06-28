<?php

namespace App\Models;

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
            'price_source_type' => InvestmentAssetPriceSource::class,
            'price_source_config' => 'array',
        ];
    }
}
