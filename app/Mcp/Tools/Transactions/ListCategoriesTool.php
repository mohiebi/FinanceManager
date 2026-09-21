<?php

namespace App\Mcp\Tools\Transactions;

use App\Models\Category;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the categories available to the user: global default categories plus their own custom ones. A category applies to its `type`, or to both costs and income when `for_both_types` is true; a transaction\'s type must be one its category allows. Categories may have one level of subcategories (`parent_id`, `parent`, `path` like "Food › Restaurant"): a transaction can use either, and a parent\'s totals include its subcategories. Parents are listed with their subcategories right after them.')]
class ListCategoriesTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        $categories = Category::orderedByParent(
            Category::query()
                ->availableFor($user)
                ->with('parent:id,name')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
        )->map(fn (Category $category): array => [
            'id' => $category->id,
            'name' => $category->name,
            'type' => $category->type->value,
            'for_both_types' => $category->for_both_types,
            'parent_id' => $category->parent_id,
            'parent' => $category->parent?->name,
            'path' => $category->pathName(),
            'is_default' => $category->is_default,
        ]);

        return Response::structured(['categories' => $categories->all()]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
