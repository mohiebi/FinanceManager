<?php

namespace App\Models;

use Database\Factories\AdvisorProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'investor_assessment_id', 'profile_version', 'profile_payload', 'ai_consent_at'])]
class AdvisorProfile extends Model
{
    /** @use HasFactory<AdvisorProfileFactory> */
    use HasFactory;

    /** @return BelongsTo<User, AdvisorProfile> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<InvestorAssessment, AdvisorProfile> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(InvestorAssessment::class, 'investor_assessment_id');
    }

    /** @return HasMany<AdvisorRecommendation, AdvisorProfile> */
    public function recommendations(): HasMany
    {
        return $this->hasMany(AdvisorRecommendation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            // This minimized derived context is intentionally server-readable,
            // with explicit consent, so Advisor still works while Vault is armed.
            // Raw assessment answers and holdings remain encrypted elsewhere.
            'profile_payload' => 'array',
            'ai_consent_at' => 'datetime',
        ];
    }
}
