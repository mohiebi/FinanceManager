<?php

namespace App\Models;

use Database\Factories\SocialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'provider', 'provider_user_id', 'provider_email', 'avatar'])]
class SocialAccount extends Model
{
    public const ProviderGoogle = 'google';

    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, SocialAccount>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
