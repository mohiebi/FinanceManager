<?php

namespace App\Support\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Passport\Token;

/**
 * Who is actually using the AI assistant.
 *
 * A user counts when they hold a Passport token that is live and carries the
 * MCP scope — the same test AiConnectionsController applies on the settings
 * page, but expressed in SQL.
 *
 * That difference matters. The settings page loads one user's tokens and filters
 * them in PHP with `$token->can('mcp:use')`, which is fine for a handful of rows
 * and quite wrong for an aggregate over every account. Doing it in SQL means
 * reproducing what `can()` decides, including its wildcard rule — a token scoped
 * `*` grants everything, so a query looking only for the literal scope would
 * quietly undercount every such connection.
 */
final class McpTokenQuery
{
    /** The scope the MCP server requires. */
    public const SCOPE = 'mcp:use';

    /** Passport's wildcard, which Token::can() treats as granting everything. */
    private const WILDCARD = '*';

    /**
     * Live tokens that may reach the MCP server.
     *
     * @return Builder<Token>
     */
    public static function live(): Builder
    {
        return Token::query()
            ->where('revoked', false)
            ->where(fn (Builder $query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->where(fn (Builder $query) => $query
                ->whereJsonContains('scopes', self::SCOPE)
                ->orWhereJsonContains('scopes', self::WILDCARD));
    }

    /**
     * Constrain a user query to those with a live MCP connection.
     *
     * @param  Builder<User>  $query
     */
    public static function connected(Builder $query): void
    {
        $query->whereIn('id', self::live()->select('user_id'));
    }

    /**
     * @param  Builder<User>  $query
     */
    public static function disconnected(Builder $query): void
    {
        $query->whereNotIn('id', self::live()->select('user_id'));
    }
}
