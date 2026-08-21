<?php

namespace App\Models;

use App\Casts\UserEncrypted;
use App\Concerns\OwnsEncryptedAttributes;
use App\Concerns\ScopedToOwner;
use App\Contracts\HasEncryptionOwner;
use App\Enums\BillRecurrenceLimitType;
use App\Enums\BillRecurrenceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
    'category_id',
    'title',
    'amount',
    'currency',
    'recurrence_type',
    'due_day_of_month',
    'due_date',
    'recurrence_limit_type',
    'recurrence_count',
    'recurrence_end_date',
    'telegram_reminder_enabled',
    'reminder_time',
    'reminder_timezone',
    'is_active',
])]
class Bill extends Model implements HasEncryptionOwner
{
    use OwnsEncryptedAttributes, ScopedToOwner;

    /**
     * @return BelongsTo<User, Bill>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Category, Bill>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<BillOccurrence, Bill>
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(BillOccurrence::class);
    }

    /**
     * The single most recently paid occurrence, if any — used to show a
     * bill as "Paid" for a short window after it lands, instead of jumping
     * straight to whatever its next scheduled occurrence happens to be.
     *
     * @return HasOne<BillOccurrence, Bill>
     */
    public function latestPaidOccurrence(): HasOne
    {
        return $this->hasOne(BillOccurrence::class)
            ->whereNotNull('paid_at')
            ->latestOfMany('paid_at');
    }

    public function isRecurring(): bool
    {
        return $this->recurrence_type === BillRecurrenceType::Monthly;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'title' => UserEncrypted::class,
            // Plain string rather than ':decimal,2' on purpose — it preserves the
            // exact shape the old APP_KEY 'encrypted' cast returned, so moving the
            // key changes nothing downstream.
            'amount' => UserEncrypted::class,
            'recurrence_type' => BillRecurrenceType::class,
            'due_date' => 'date:Y-m-d',
            'recurrence_limit_type' => BillRecurrenceLimitType::class,
            'recurrence_count' => 'integer',
            'recurrence_end_date' => 'date:Y-m-d',
            'telegram_reminder_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
