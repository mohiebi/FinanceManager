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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the user\'s pending (not yet confirmed, rejected, or expired) change proposals.')]
class ListPendingProposalsTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        $proposals = McpProposal::query()
            ->where('user_id', $user->id)
            ->where('status', McpProposalStatus::Pending)
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (McpProposal $proposal): array => [
                'proposal_id' => $proposal->id,
                'action' => $proposal->action,
                'resource_type' => $proposal->resource_type,
                'resource_id' => $proposal->resource_id,
                'diff' => $proposal->diff_summary,
                'expires_at' => $proposal->expires_at->toIso8601String(),
            ]);

        return Response::structured(['proposals' => $proposals->all()]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
