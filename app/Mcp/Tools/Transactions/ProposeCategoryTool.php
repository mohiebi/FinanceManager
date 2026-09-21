<?php

namespace App\Mcp\Tools\Transactions;

use App\Enums\TransactionType;
use App\Mcp\Support\ProposalService;
use App\Models\User;
use App\Support\CategoryRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Propose creating a custom category for the user. This does NOT change any data: it returns a diff and a proposal_id — show it to the user, get approval, then call confirm-proposal.')]
class ProposeCategoryTool extends Tool
{
    public function __construct(private readonly ProposalService $proposals) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        $validated = $request->validate([
            'type' => ['required', Rule::enum(TransactionType::class)],
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'integer'],
            'for_both_types' => ['nullable', 'boolean'],
        ]);

        $name = trim($validated['name']);
        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;
        $forBothTypes = (bool) ($validated['for_both_types'] ?? false);

        // The same rules the settings page enforces, so a proposal the user
        // approves cannot then fail on the parent or the name.
        $errors = CategoryRules::errors(
            $user,
            TransactionType::from($validated['type']),
            $forBothTypes,
            $parentId,
            $name,
        );

        if ($errors !== []) {
            return Response::error(implode(' ', $errors));
        }

        $payload = [
            'type' => $validated['type'],
            'name' => $name,
            'parent_id' => $parentId,
            'for_both_types' => $forBothTypes,
        ];

        $proposal = $this->proposals->propose(
            $user,
            'create',
            'category',
            null,
            $payload,
            $this->proposals->diff($payload),
        );

        return $this->proposals->toResponse($proposal);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(['cost', 'income'])->description('Whether the category applies to costs or income.')->required(),
            'name' => $schema->string()->description('Name of the new category.')->required(),
            'parent_id' => $schema->integer()->description('Optional parent category id (see list-categories) to create this as a subcategory. One level only: the parent must itself be top-level, and must allow this category\'s type.'),
            'for_both_types' => $schema->boolean()->description('Optional. Make the category usable for both costs and income. A shared subcategory needs a shared parent.'),
        ];
    }
}
