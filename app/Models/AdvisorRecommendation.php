<?php

namespace App\Models;

use App\Casts\UserEncrypted;
use App\Concerns\OwnsEncryptedAttributes;
use App\Contracts\HasEncryptionOwner;
use App\Enums\AdvisorRecommendationMode;
use App\Enums\AdvisorRecommendationStatus;
use Database\Factories\AdvisorRecommendationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'advisor_profile_id', 'status', 'pending_status', 'mode', 'profile_version', 'scoring_version', 'prompt_version', 'provider', 'model', 'knowledge_version', 'context_hash', 'output_hash', 'current_portfolio_included', 'current_portfolio_snapshot', 'clarification_answers', 'recommendation_payload', 'failure_code', 'clarification_rounds', 'repair_attempts', 'provider_calls', 'generated_at'])]
class AdvisorRecommendation extends Model implements HasEncryptionOwner
{
    /** @use HasFactory<AdvisorRecommendationFactory> */
    use HasFactory, HasUlids, OwnsEncryptedAttributes;

    /** @return BelongsTo<User, AdvisorRecommendation> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AdvisorProfile, AdvisorRecommendation> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(AdvisorProfile::class, 'advisor_profile_id');
    }

    /** @return HasMany<AdvisorMessage, AdvisorRecommendation> */
    public function messages(): HasMany
    {
        return $this->hasMany(AdvisorMessage::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => AdvisorRecommendationStatus::class,
            'pending_status' => AdvisorRecommendationStatus::class,
            'mode' => AdvisorRecommendationMode::class,
            'current_portfolio_included' => 'boolean',
            'current_portfolio_snapshot' => UserEncrypted::class.':json',
            'clarification_answers' => UserEncrypted::class.':json',
            'recommendation_payload' => UserEncrypted::class.':json',
            'generated_at' => 'datetime',
        ];
    }
}
