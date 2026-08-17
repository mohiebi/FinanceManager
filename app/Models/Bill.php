<?php

namespace App\Models;

use App\Casts\UserEncrypted;
use App\Concerns\OwnsEncryptedAttributes;
use App\Concerns\ScopedToOwner;
use App\Contracts\HasEncryptionOwner;
use App\Enums\BillRecurrenceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'category_id',
    'title',
    'amount',
    'currency',
    'recurrence_type',
    'due_day_of_month',
    'due_date',
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
            'telegram_reminder_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
