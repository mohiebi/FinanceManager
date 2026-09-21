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

#[Fillable(['user_id', 'parent_id', 'type', 'for_both_types', 'name', 'slug', 'color', 'sort_order', 'is_default'])]
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

    /**
     * The transaction types this category may be used for.
     *
     * `type` is the home type; a shared category is usable for both, while
     * still listed and uniquely named under its home type.
     *
     * @return array<int, TransactionType>
     */
    public function allowedTypes(): array
    {
        return $this->for_both_types ? TransactionType::cases() : [$this->type];
    }

    public function allowsType(TransactionType $type): bool
    {
        return in_array($type, $this->allowedTypes(), true);
    }

    /**
     * A category's ids plus its children's, for anything that treats a parent
     * as the whole family — the transaction filter and a budget line on it.
     *
     * Children are scoped to the user: a shared default parent such as Food
     * has other people's subcategories under it too.
     *
     * @param  array<int, int>  $categoryIds
     * @return array<int, array<int, int>> parent id => [itself, ...its children]
     */
    public static function familiesFor(User $user, array $categoryIds): array
    {
        $families = [];

        foreach ($categoryIds as $categoryId) {
            $families[(int) $categoryId] = [(int) $categoryId];
        }

        if ($families === []) {
            return [];
        }

        self::query()
            ->availableFor($user)
            ->whereIn('parent_id', array_keys($families))
            ->get(['id', 'parent_id'])
            ->each(function (Category $child) use (&$families): void {
                $families[(int) $child->parent_id][] = (int) $child->id;
            });

        return $families;
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
     * @return BelongsTo<Category, Category>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, Category>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Transaction, Category>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Categories a transaction of this type may use: its own type's, plus
     * every shared one whatever its home type.
     */
    #[Scope]
    protected function forType(Builder $query, TransactionType $type): void
    {
        $query->where(function (Builder $query) use ($type): void {
            $query->where('type', $type)
                ->orWhere('for_both_types', true);
        });
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
            'for_both_types' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
