<?php

namespace App\Models;

use App\Casts\UserEncrypted;
use App\Concerns\OwnsEncryptedAttributes;
use App\Contracts\HasEncryptionOwner;
use Database\Factories\InvestorAssessmentAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'investor_assessment_id', 'question_key', 'answer'])]
class InvestorAssessmentAnswer extends Model implements HasEncryptionOwner
{
    /** @use HasFactory<InvestorAssessmentAnswerFactory> */
    use HasFactory, OwnsEncryptedAttributes;

    /** @return BelongsTo<User, InvestorAssessmentAnswer> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<InvestorAssessment, InvestorAssessmentAnswer> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(InvestorAssessment::class, 'investor_assessment_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['answer' => UserEncrypted::class.':json'];
    }
}
