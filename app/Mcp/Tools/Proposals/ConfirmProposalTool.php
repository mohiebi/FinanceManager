<?php

namespace App\Mcp\Tools\Proposals;

use App\Enums\McpProposalStatus;
use App\Mcp\Support\ProposalApplier;
use App\Models\McpProposal;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Apply a pending proposal after the user has explicitly approved it in this conversation. Only call this once the user has clearly said yes to the exact change shown to them. Each proposal can be confirmed at most once and expires 10 minutes after it was created.')]
class ConfirmProposalTool extends Tool
{
    public function __construct(private readonly ProposalApplier $applier) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        $validated = $request->validate([
            'proposal_id' => ['required', 'string'],
        ]);

        try {
            $result = DB::transaction(function () use ($user, $validated): array {
                // Row lock so a double confirm (client retry, duplicated call)
                // cannot apply the same proposal twice: the second caller
                // blocks here, then sees the consumed status.
                $proposal = McpProposal::query()
                    ->whereKey($validated['proposal_id'])
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if (! $proposal instanceof McpProposal) {
                    return ['error' => 'Proposal not found.'];
                }

                if (! $proposal->isPending()) {
                    return ['error' => "This proposal was already {$proposal->status->value} and cannot be applied again."];
                }

                if ($proposal->isExpired()) {
                    $proposal->update(['status' => McpProposalStatus::Expired]);

                    return ['error' => 'This proposal has expired. Propose the change again if the user still wants it.'];
                }

                $applied = $this->applier->apply($proposal);

                $proposal->update([
                    'status' => McpProposalStatus::Confirmed,
                    'consumed_at' => now(),
                ]);

                return ['applied' => $applied, 'proposal' => $proposal];
            });
        } catch (ValidationException $exception) {
            return Response::error('The proposed change is no longer valid: '.implode(' ', $exception->validator->errors()->all()));
        }

        if (isset($result['error'])) {
            return Response::error($result['error']);
        }

        /** @var McpProposal $proposal */
        $proposal = $result['proposal'];

        return Response::structured([
            'status' => 'confirmed',
            'proposal_id' => $proposal->id,
            'action' => $proposal->action,
            'resource_type' => $proposal->resource_type,
            'result' => $result['applied'],
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
