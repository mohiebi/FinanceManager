<?php

namespace App\Models;

use App\Enums\McpProposalStatus;
use Database\Factories\McpProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pending or resolved change proposed by an AI client over MCP. Mutations
 * are never applied directly: a proposal is stored first, and only an
 * explicit confirmation applies its payload — exactly once, before expiry.
 * Resolved proposals double as the user's audit history.
 */
#[Fillable([
    'user_id',
    'oauth_client_id',
    'client_name',
    'action',
    'resource_type',
    'resource_id',
    'payload',
    'diff_summary',
    'status',
    'expires_at',
    'consumed_at',
])]
class McpProposal extends Model
{
    /** @use HasFactory<McpProposalFactory> */
    use HasFactory, HasUlids, MassPrunable;

    /**
     * @return BelongsTo<User, McpProposal>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === McpProposalStatus::Pending;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Audit history is kept for 90 days, then pruned by the scheduler.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<=', now()->subDays(90));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:json',
            'diff_summary' => 'encrypted:json',
            'status' => McpProposalStatus::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
