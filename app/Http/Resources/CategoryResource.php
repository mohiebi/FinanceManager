<?php

namespace App\Http\Resources;

use App\Enums\TransactionType;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * Categories split into the list each transaction type's picker offers.
     *
     * A shared category is listed under both, so a picker keyed by type works
     * without knowing sharing exists.
     *
     * @param  Collection<int, Category>  $categories
     * @return array<string, Collection<int, array<string, mixed>>>
     */
    public static function groupedByType(Collection $categories, Request $request): array
    {
        return collect(TransactionType::cases())
            ->mapWithKeys(fn (TransactionType $type): array => [
                $type->value => $categories
                    ->filter(fn (Category $category): bool => $category->allowsType($type))
                    ->values()
                    ->map(fn (Category $category): array => (new self($category))->resolve($request)),
            ])
            ->all();
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $name = $this->name;

        if ($this->is_default) {
            $translationKey = "finance.categories.{$this->type->value}.{$this->slug}";
            $name = __($translationKey) === $translationKey ? $this->name : __($translationKey);
        }

        return [
            'id' => $this->id,
            'name' => $name,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'for_both_types' => $this->for_both_types,
            'parent_id' => $this->parent_id,
            'color' => $this->resolvedColor(),
            'is_default' => $this->is_default,
        ];
    }
}
