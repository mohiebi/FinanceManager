<?php

namespace App\Mcp\Support;

use App\Enums\McpProposalStatus;
use App\Models\McpProposal;
use App\Models\User;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Passport\Passport;

/**
 * Stores MCP change proposals and formats the standard proposal response.
 * Nothing is written to finance data at proposal time — the AI client must
 * relay the diff to the user and call confirm-proposal after approval.
 */
class ProposalService
{
    public const EXPIRY_MINUTES = 10;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $diff
     */
    public function propose(
        User $user,
        string $action,
        string $resourceType,
        ?int $resourceId,
        array $payload,
        array $diff,
    ): McpProposal {
        [$clientId, $clientName] = $this->clientIdentity($user);

        return McpProposal::query()->create([
            'user_id' => $user->id,
            'oauth_client_id' => $clientId,
            'client_name' => $clientName,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'payload' => $payload,
            'diff_summary' => $diff,
            'status' => McpProposalStatus::Pending,
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ]);
    }

    public function toResponse(McpProposal $proposal): Response|ResponseFactory
    {
        return Response::structured([
            'proposal_id' => $proposal->id,
            'action' => $proposal->action,
            'resource_type' => $proposal->resource_type,
            'resource_id' => $proposal->resource_id,
            'diff' => $proposal->diff_summary,
            'expires_at' => $proposal->expires_at->toIso8601String(),
            'next_step' => 'Show this change to the user and ask for their approval. Once they approve, call the confirm-proposal tool with this proposal_id. Nothing is saved until confirmed.',
        ]);
    }

    /**
     * Builds an old/new diff limited to keys present in the new attributes.
     *
     * @param  array<string, mixed>  $new
     * @param  array<string, mixed>  $old
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function diff(array $new, array $old = []): array
    {
        $diff = [];

        foreach ($new as $key => $value) {
            $previous = $old[$key] ?? null;

            if ($previous !== $value) {
                $diff[$key] = ['old' => $previous, 'new' => $value];
            }
        }

        return $diff;
    }

    /**
     * Resolves the OAuth client from the access token Passport already attached
     * to the authenticated user.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function clientIdentity(User $user): array
    {
        $token = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        $clientId = $token?->oauth_client_id;

        if (blank($clientId)) {
            return [null, null];
        }

        $client = Passport::client()->newQuery()->find($clientId);

        if ($client === null) {
            return [null, null];
        }

        return [(string) $client->getKey(), $client->name];
    }
}
