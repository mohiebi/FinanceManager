<?php

namespace App\Models;

use App\Enums\ScreeningRisk;
use App\Enums\ScreeningStage;
use Database\Factories\PaymentScreeningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'subscription_payment_id',
    'stage',
    'risk',
    'provider',
    'categories',
    'provider_reference',
    'error_code',
    'screened_at',
])]
class PaymentScreening extends Model
{
    /** @use HasFactory<PaymentScreeningFactory> */
    use HasFactory, HasUlids;

    /** @return BelongsTo<SubscriptionPayment, PaymentScreening> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'subscription_payment_id');
    }

    protected function casts(): array
    {
        return [
            'stage' => ScreeningStage::class,
            'risk' => ScreeningRisk::class,
            'categories' => 'array',
            'screened_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Screening attempts are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Screening attempts are append-only.'));
    }
}
