<?php

namespace App\Models;

use App\Enums\SettlementStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['subscription_payment_id', 'operation_id', 'status', 'risk_authorized'])]
class PaymentSettlement extends Model
{
    use HasUlids;

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'subscription_payment_id');
    }

    protected function casts(): array
    {
        return [
            'status' => SettlementStatus::class,
            'risk_authorized' => 'boolean',
            'transaction_hashes' => 'array',
            'attempts' => 'integer',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
