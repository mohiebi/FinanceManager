<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\McpProposal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

class AiConnectionsController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        /** @var Collection<int, Token> $tokens */
        $tokens = Token::query()
            ->where('user_id', $user->id)
            ->where('revoked', false)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with('client')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (Token $token): bool => $token->can('mcp:use'));

        $connections = $tokens
            ->groupBy('client_id')
            ->map(function (Collection $clientTokens): array {
                /** @var Token $oldestToken */
                $oldestToken = $clientTokens->sortBy('created_at')->first();
                /** @var Token $earliestExpiringToken */
                $earliestExpiringToken = $clientTokens->sortBy('expires_at')->first();

                return [
                    'id' => (string) $oldestToken->client_id,
                    'client_name' => $oldestToken->client?->name ?? 'Unknown application',
                    'created_at' => $oldestToken->created_at?->toIso8601String(),
                    'expires_at' => $earliestExpiringToken->expires_at?->toIso8601String(),
                    'active_sessions' => $clientTokens->count(),
                ];
            })
            ->values();

        $history = McpProposal::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (McpProposal $proposal): array => [
                'id' => $proposal->id,
                'client_name' => $proposal->client_name,
                'action' => $proposal->action,
                'resource_type' => $proposal->resource_type,
                'status' => $proposal->status->value,
                'diff' => $proposal->diff_summary,
                'created_at' => $proposal->created_at->toIso8601String(),
                'consumed_at' => $proposal->consumed_at?->toIso8601String(),
            ]);

        return Inertia::render('settings/AiConnections', [
            'connections' => $connections,
            'history' => $history,
            'mcpUrl' => url('/mcp/finance'),
        ]);
    }

    /**
     * The emergency stop: every active MCP token for this user, across every
     * connected client, revoked in one call. Same two-table update as a single
     * client's revoke, just scoped to the user instead of one client_id.
     */
    public function revokeAll(Request $request): RedirectResponse
    {
        /** @var Collection<int, Token> $tokens */
        $tokens = Token::query()
            ->where('user_id', $request->user()->id)
            ->where('revoked', false)
            ->get()
            ->filter(fn (Token $token): bool => $token->can('mcp:use'));

        if ($tokens->isEmpty()) {
            return back();
        }

        $tokenIds = $tokens->pluck('id');

        Token::query()->whereIn('id', $tokenIds)->update(['revoked' => true]);

        RefreshToken::query()
            ->whereIn('access_token_id', $tokenIds)
            ->update(['revoked' => true]);

        return back();
    }

    public function destroy(Request $request, string $clientId): RedirectResponse
    {
        /** @var Collection<int, Token> $tokens */
        $tokens = Token::query()
            ->where('user_id', $request->user()->id)
            ->where('client_id', $clientId)
            ->where('revoked', false)
            ->get()
            ->filter(fn (Token $token): bool => $token->can('mcp:use'));

        abort_if($tokens->isEmpty(), 404);

        $tokenIds = $tokens->pluck('id');

        Token::query()->whereIn('id', $tokenIds)->update(['revoked' => true]);

        RefreshToken::query()
            ->whereIn('access_token_id', $tokenIds)
            ->update(['revoked' => true]);

        return back();
    }
}
