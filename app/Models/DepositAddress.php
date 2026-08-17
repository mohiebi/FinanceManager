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

#[Fillable(['network', 'derivation_index', 'address', 'status'])]
class DepositAddress extends Model
{
    /** @use HasFactory<DepositAddressFactory> */
    use HasFactory, HasUlids;

    /** @return BelongsTo<SubscriptionPayment, DepositAddress> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'assigned_payment_id');
    }

    /** @return BelongsTo<User, DepositAddress> */
    public function sweepAuthorizedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sweep_authorized_by_admin_id');
    }

    /** @return BelongsTo<User, DepositAddress> */
    public function sweptByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'swept_by_admin_id');
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
            'sweep_authorized_at' => 'datetime',
            'sweep_authorization_expires_at' => 'datetime',
            'swept_at' => 'datetime',
        ];
    }
}
