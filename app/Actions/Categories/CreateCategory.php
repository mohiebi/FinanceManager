<?php

namespace App\Actions\Categories;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\User;
use App\Support\CategoryRules;
use InvalidArgumentException;

/**
 * Creates a custom category for the MCP surfaces, under the same rules the
 * settings page enforces.
 *
 * A proposal can wait a while between being made and confirmed, so the rules
 * run again here rather than trusting what held when it was proposed: the
 * parent may since have been deleted, or have become a subcategory itself.
 */
class CreateCategory
{
    /**
     * @param  array{type: string, name: string, parent_id?: int|null, for_both_types?: bool}  $data
     *
     * @throws InvalidArgumentException
     */
    public function handle(User $user, array $data): Category
    {
        $type = TransactionType::from($data['type']);
        $name = trim($data['name']);
        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
        $forBothTypes = (bool) ($data['for_both_types'] ?? false);

        $errors = CategoryRules::errors($user, $type, $forBothTypes, $parentId, $name);

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        return Category::query()->create([
            'user_id' => $user->id,
            'parent_id' => $parentId,
            'type' => $type,
            'for_both_types' => $forBothTypes,
            'name' => $name,
        ]);
    }
}
