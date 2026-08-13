<?php

namespace App\Models;

use App\Casts\UserEncrypted;
use App\Concerns\OwnsEncryptedAttributes;
use App\Contracts\HasEncryptionOwner;
use Database\Factories\AdvisorMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'advisor_recommendation_id', 'role', 'payload'])]
class AdvisorMessage extends Model implements HasEncryptionOwner
{
    /** @use HasFactory<AdvisorMessageFactory> */
    use HasFactory, HasUlids, OwnsEncryptedAttributes;

    /** @return BelongsTo<User, AdvisorMessage> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AdvisorRecommendation, AdvisorMessage> */
    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(AdvisorRecommendation::class, 'advisor_recommendation_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['payload' => UserEncrypted::class.':json'];
    }
}
