<?php

namespace App\Mcp\Tools\Transactions;

use App\Enums\TransactionType;
use App\Mcp\Support\ProposalService;
use App\Models\Category;
use App\Models\User;
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
        ]);

        $name = trim($validated['name']);

        $exists = Category::query()
            ->availableFor($user)
            ->where('type', $validated['type'])
            ->where('slug', Category::slugForName($name))
            ->exists();

        if ($exists) {
            return Response::error('A category with this name already exists for this type.');
        }

        $payload = ['type' => $validated['type'], 'name' => $name];

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
        ];
    }
}
