<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bill_id',
    'transaction_id',
    'due_date',
    'paid_at',
    'reminder_day_before_sent_at',
    'reminder_due_day_sent_at',
])]
class BillOccurrence extends Model
{
    /**
     * @return BelongsTo<Bill, BillOccurrence>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /**
     * @return BelongsTo<Transaction, BillOccurrence>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
            'paid_at' => 'datetime',
            'reminder_day_before_sent_at' => 'datetime',
            'reminder_due_day_sent_at' => 'datetime',
        ];
    }
}
