<?php

namespace App\Models;

use App\Enums\TransactionType;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'type', 'name', 'slug', 'color', 'sort_order', 'is_default'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * @var array<string, array<string, string>>
     */
    public const DEFAULT_COLORS = [
        TransactionType::Cost->value => [
            'food' => '#F59E0B',
            'transport' => '#3B82F6',
            'housing' => '#6B7280',
            'health' => '#E94E50',
            'shopping' => '#947BFF',
            'bills' => '#F97316',
            'investment' => '#02CD86',
            'other' => '#686868',
        ],
        TransactionType::Income->value => [
            'salary' => '#02CD86',
            'freelance' => '#947BFF',
            'gift' => '#F59E0B',
            'other' => '#686868',
        ],
    ];

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            if (! $category->slug) {
                $category->slug = self::slugForName($category->name);
            }

            $category->is_default = $category->user_id === null;
        });
    }

    public static function slugForName(string $name): string
    {
        $slug = Str::slug($name);

        if ($slug !== '') {
            return $slug;
        }

        return 'category-'.substr(sha1(mb_strtolower(trim($name))), 0, 16);
    }

    public function resolvedColor(): ?string
    {
        if ($this->color !== null) {
            return $this->color;
        }

        return self::DEFAULT_COLORS[$this->type->value][$this->slug] ?? null;
    }

    /**
     * @return BelongsTo<User, Category>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Transaction, Category>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
