<?php

namespace App\Models;

use App\Enums\PaymentNetwork;
use App\Enums\SettlementStatus;
use Database\Factories\DepositRecoveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['deposit_address_id', 'network', 'operation_id', 'status', 'reason'])]
class DepositRecovery extends Model
{
    /** @use HasFactory<DepositRecoveryFactory> */
    use HasFactory, HasUlids;

    /** @return BelongsTo<DepositAddress, DepositRecovery> */
    public function depositAddress(): BelongsTo
    {
        return $this->belongsTo(DepositAddress::class);
    }

    protected function casts(): array
    {
        return [
            'network' => PaymentNetwork::class,
            'status' => SettlementStatus::class,
            'transaction_hashes' => 'array',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
