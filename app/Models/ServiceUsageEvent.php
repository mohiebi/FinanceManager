<?php

namespace App\Models;

use Database\Factories\ServiceUsageEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'service', 'operation', 'source_type', 'source_id', 'provider', 'model', 'prompt_tokens', 'completion_tokens', 'provider_cost_usd', 'latency_ms', 'outcome', 'shadow_miles', 'charged_miles', 'metadata'])]
class ServiceUsageEvent extends Model
{
    /** @use HasFactory<ServiceUsageEventFactory> */
    use HasFactory, HasUlids;

    public const UPDATED_AT = null;

    /** @return BelongsTo<User, ServiceUsageEvent> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, ServiceUsageEvent> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['prompt_tokens' => 'integer', 'completion_tokens' => 'integer', 'provider_cost_usd' => 'decimal:8', 'latency_ms' => 'integer', 'shadow_miles' => 'integer', 'charged_miles' => 'integer', 'metadata' => 'array'];
    }
}
