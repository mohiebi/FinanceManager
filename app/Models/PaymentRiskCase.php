<?php

namespace App\Models;

use App\Enums\PaymentNetwork;
use App\Enums\RiskCaseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['subscription_payment_id', 'user_id', 'network', 'source_address', 'user_case_number', 'status', 'review_expires_at'])]
class PaymentRiskCase extends Model
{
    use HasUlids;

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'subscription_payment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'network' => PaymentNetwork::class,
            'user_case_number' => 'integer',
            'status' => RiskCaseStatus::class,
            'review_expires_at' => 'datetime',
            'authorized_at' => 'datetime',
            'granted_at' => 'datetime',
        ];
    }
}
