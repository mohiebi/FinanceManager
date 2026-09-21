<?php

namespace App\Support;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The hierarchy and shared-type rules a category has to satisfy, shared by
 * the create and update requests so both enforce the same shape.
 *
 * One level only: a parent is always top-level and a category with children
 * can never become a child. A child is usable for a subset of its parent's
 * types, so it never appears in a picker without its parent.
 */
class CategoryRules
{
    /**
     * @return array<string, string> field => error message
     */
    public static function errors(
        User $user,
        TransactionType $type,
        bool $forBothTypes,
        ?int $parentId,
        string $name,
        ?Category $category = null,
    ): array {
        $types = $forBothTypes ? TransactionType::cases() : [$type];

        return [
            ...self::nameErrors($user, $types, $name, $category),
            ...self::parentErrors($user, $types, $forBothTypes, $parentId, $category),
            ...($category === null ? [] : self::narrowingErrors($category, $types)),
        ];
    }

    /**
     * A name may appear once per type a category is usable for, so sharing a
     * category also claims its name on the other side.
     *
     * @param  array<int, TransactionType>  $types
     * @return array<string, string>
     */
    private static function nameErrors(User $user, array $types, string $name, ?Category $category): array
    {
        if ($name === '') {
            return [];
        }

        $taken = Category::query()
            ->availableFor($user)
            ->where('slug', Category::slugForName($name))
            ->when($category !== null, fn (Builder $query) => $query->whereKeyNot($category->id))
            ->where(function (Builder $query) use ($types): void {
                $query->whereIn('type', $types)
                    ->orWhere('for_both_types', true);
            })
            ->exists();

        return $taken ? ['name' => __('finance.categories.already_exists')] : [];
    }

    /**
     * @param  array<int, TransactionType>  $types
     * @return array<string, string>
     */
    private static function parentErrors(
        User $user,
        array $types,
        bool $forBothTypes,
        ?int $parentId,
        ?Category $category,
    ): array {
        if ($parentId === null) {
            return [];
        }

        if ($category !== null && $parentId === $category->id) {
            return ['parent_id' => __('finance.categories.parent_is_self')];
        }

        $parent = Category::query()->availableFor($user)->find($parentId);

        if (! $parent instanceof Category) {
            return ['parent_id' => __('finance.categories.parent_invalid')];
        }

        if ($parent->parent_id !== null) {
            return ['parent_id' => __('finance.categories.parent_not_top_level')];
        }

        if ($category !== null && $category->children()->exists()) {
            return ['parent_id' => __('finance.categories.has_children')];
        }

        foreach ($types as $type) {
            if (! $parent->allowsType($type)) {
                return [
                    $forBothTypes ? 'for_both_types' : 'parent_id' => __('finance.categories.parent_type_mismatch'),
                ];
            }
        }

        return [];
    }

    /**
     * Unsharing a category takes a type away, which strands anything already
     * using it for that type: its transactions, and any child that needs it.
     *
     * @param  array<int, TransactionType>  $types
     * @return array<string, string>
     */
    private static function narrowingErrors(Category $category, array $types): array
    {
        $removed = array_values(array_filter(
            $category->allowedTypes(),
            fn (TransactionType $type): bool => ! in_array($type, $types, true),
        ));

        if ($removed === []) {
            return [];
        }

        $stranded = $category->transactions()->whereIn('type', $removed)->count();

        if ($stranded > 0) {
            return ['for_both_types' => __('finance.categories.unshare_in_use', ['count' => $stranded])];
        }

        $childNeedsRemovedType = $category->children()
            ->where(function (Builder $query) use ($removed): void {
                $query->whereIn('type', $removed)
                    ->orWhere('for_both_types', true);
            })
            ->exists();

        return $childNeedsRemovedType
            ? ['for_both_types' => __('finance.categories.children_need_shared')]
            : [];
    }
}
