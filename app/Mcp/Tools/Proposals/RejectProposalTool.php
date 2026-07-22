<?php

namespace App\Mcp\Tools\Proposals;

use App\Enums\McpProposalStatus;
use App\Models\McpProposal;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Reject a pending proposal when the user declines the change. The proposal is recorded as rejected in the audit history and can never be applied.')]
class RejectProposalTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        $validated = $request->validate([
            'proposal_id' => ['required', 'string'],
        ]);

        $proposal = McpProposal::query()
            ->whereKey($validated['proposal_id'])
            ->where('user_id', $user->id)
            ->first();

        if (! $proposal instanceof McpProposal) {
            return Response::error('Proposal not found.');
        }

        if (! $proposal->isPending()) {
            return Response::error("This proposal was already {$proposal->status->value}.");
        }

        $proposal->update([
            'status' => McpProposalStatus::Rejected,
            'consumed_at' => now(),
        ]);

        return Response::structured([
            'status' => 'rejected',
            'proposal_id' => $proposal->id,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'proposal_id' => $schema->string()->description('The proposal_id returned by a propose-* tool.')->required(),
        ];
    }
}
