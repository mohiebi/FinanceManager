<?php

namespace App\Models;

use Database\Factories\AuthChallengeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'email',
    'purpose',
    'code_hash',
    'expires_at',
    'verified_at',
    'consumed_at',
    'attempts',
    'last_sent_at',
    'ip_address',
])]
class AuthChallenge extends Model
{
    public const PurposeSignup = 'signup';

    public const PurposeRecovery = 'recovery';

    /** @use HasFactory<AuthChallengeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }
}
