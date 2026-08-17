<?php

namespace App\Models;

use App\Concerns\ScopedToOwner;
use App\Enums\InvestorAssessmentStatus;
use Database\Factories\InvestorAssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'status', 'assessment_version', 'scoring_version', 'last_completed_section', 'scoring_origin', 'started_at', 'completed_at'])]
class InvestorAssessment extends Model
{
    /** @use HasFactory<InvestorAssessmentFactory> */
    use HasFactory, ScopedToOwner;

    /** @return BelongsTo<User, InvestorAssessment> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<InvestorAssessmentAnswer, InvestorAssessment> */
    public function answers(): HasMany
    {
        return $this->hasMany(InvestorAssessmentAnswer::class);
    }

    /** @return HasOne<AdvisorProfile, InvestorAssessment> */
    public function profile(): HasOne
    {
        return $this->hasOne(AdvisorProfile::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === InvestorAssessmentStatus::Completed;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => InvestorAssessmentStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
