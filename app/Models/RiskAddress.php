<?php

namespace App\Models;

use App\Enums\PaymentNetwork;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['network', 'address', 'source', 'reason', 'active', 'created_by_admin_id'])]
class RiskAddress extends Model
{
    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }

    protected function casts(): array
    {
        return [
            'network' => PaymentNetwork::class,
            'active' => 'boolean',
            'deactivated_at' => 'datetime',
        ];
    }
}
