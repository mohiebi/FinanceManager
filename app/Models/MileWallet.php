<?php

namespace App\Models;

use Database\Factories\MileWalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'referral_code', 'balance', 'lifetime_earned', 'lifetime_spent', 'freezes_held'])]
class MileWallet extends Model
{
    /** @use HasFactory<MileWalletFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (MileWallet $wallet): void {
            $wallet->referral_code ??= Str::upper(Str::random(12));
        });
    }

    /** @return BelongsTo<User, MileWallet> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'lifetime_earned' => 'integer',
            'lifetime_spent' => 'integer',
            'freezes_held' => 'integer',
        ];
    }
}
