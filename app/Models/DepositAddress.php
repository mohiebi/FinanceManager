<?php

namespace App\Models;

use App\Enums\DepositAddressStatus;
use App\Enums\PaymentNetwork;
use Database\Factories\DepositAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key_version', 'network', 'derivation_index', 'address', 'status'])]
class DepositAddress extends Model
{
    /** @use HasFactory<DepositAddressFactory> */
    use HasFactory, HasUlids;

    /** @return BelongsTo<SubscriptionPayment, DepositAddress> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'assigned_payment_id');
    }

    /** @return HasMany<DepositRecovery, DepositAddress> */
    public function recoveries(): HasMany
    {
        return $this->hasMany(DepositRecovery::class);
    }

    /** @param  Builder<static>  $query */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->where('status', DepositAddressStatus::Available->value);
    }

    protected function casts(): array
    {
        return [
            'network' => PaymentNetwork::class,
            'derivation_index' => 'integer',
            'status' => DepositAddressStatus::class,
            'assigned_at' => 'datetime',
            'quarantined_at' => 'datetime',
            'swept_at' => 'datetime',
            'recovered_at' => 'datetime',
        ];
    }
}
