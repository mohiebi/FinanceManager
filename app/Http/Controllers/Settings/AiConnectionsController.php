<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\McpProposal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

class AiConnectionsController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $connections = Token::query()
            ->where('user_id', $user->id)
            ->where('revoked', false)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with('client')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Token $token): array => [
                'id' => $token->getKey(),
                'client_name' => $token->client?->name ?? 'Unknown application',
                'created_at' => $token->created_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
            ]);

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
            ]);

        return Inertia::render('settings/AiConnections', [
            'connections' => $connections,
            'history' => $history,
            'mcpUrl' => url('/mcp/finance'),
        ]);
    }

    public function destroy(Request $request, string $tokenId): RedirectResponse
    {
        $token = Token::query()
            ->whereKey($tokenId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $token->revoke();

        RefreshToken::query()
            ->where('access_token_id', $token->getKey())
            ->update(['revoked' => true]);

        return back();
    }
}
