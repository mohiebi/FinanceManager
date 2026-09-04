<?php

namespace App\Models;

use Database\Factories\MileWalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'balance', 'lifetime_earned', 'lifetime_spent', 'freezes_held'])]
class MileWallet extends Model
{
    /** @use HasFactory<MileWalletFactory> */
    use HasFactory;

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
