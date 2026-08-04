<?php

namespace App\Models;

use App\Casts\UserEncrypted;
use App\Concerns\OwnsEncryptedAttributes;
use App\Contracts\HasEncryptionOwner;
use Database\Factories\SavingsGoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'investment_asset_id',
    'title',
    'target_quantity',
    'target_date',
    'started_on',
    'achieved_on',
    'is_active',
])]
class SavingsGoal extends Model implements HasEncryptionOwner
{
    /** @use HasFactory<SavingsGoalFactory> */
    use HasFactory, OwnsEncryptedAttributes;

    /**
     * @return BelongsTo<User, SavingsGoal>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<InvestmentAsset, SavingsGoal>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(InvestmentAsset::class, 'investment_asset_id');
    }

    /**
     * @param  Builder<SavingsGoal>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // 8 decimals, unlike the 2 used for money: a target of 0.00012345 BTC
            // rounded to 2 would be a target of zero.
            'target_quantity' => UserEncrypted::class.':decimal,8',
            'title' => UserEncrypted::class,
            'target_date' => 'date:Y-m-d',
            'started_on' => 'date:Y-m-d',
            // Plaintext, unlike the target: a date the portfolio filters on, and
            // one the server can read even when the quantities are sealed.
            'achieved_on' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }
}
