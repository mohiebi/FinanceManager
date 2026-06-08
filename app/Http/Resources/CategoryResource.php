<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
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
            'is_default' => $this->is_default,
        ];
    }
}
