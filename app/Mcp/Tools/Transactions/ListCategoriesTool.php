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
#[Description('List the categories available to the user: global default categories plus their own custom ones. Each category applies to either costs or income — a transaction\'s category type must match its transaction type.')]
class ListCategoriesTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        $categories = Category::query()
            ->availableFor($user)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type->value,
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
