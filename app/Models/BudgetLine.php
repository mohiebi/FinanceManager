<?php

namespace App\Models;

use App\Casts\UserEncrypted;
use App\Concerns\OwnsEncryptedAttributes;
use App\Contracts\HasEncryptionOwner;
use App\Enums\BudgetRuleType;
use App\Enums\Currency;
use Database\Factories\BudgetLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'budget_id',
    'category_id',
    'rule_type',
    'percent',
    'fixed_amount',
    'currency',
    'rollover_enabled',
    'sort_order',
])]
class BudgetLine extends Model implements HasEncryptionOwner
{
    /** @use HasFactory<BudgetLineFactory> */
    use HasFactory, OwnsEncryptedAttributes;

    /**
     * @return BelongsTo<Budget, BudgetLine>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * @return BelongsTo<Category, BudgetLine>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The currency this line's amount is written in.
     *
     * Falls back to the budget's, which is what a line carrying no currency of
     * its own means — and what every line written before the column existed was.
     */
    public function currencyWithin(Budget $budget): Currency
    {
        return $this->currency ?? $budget->currency;
    }

    /**
     * The owner lives one hop away, on the budget.
     *
     * Overridden rather than denormalising `user_id` onto the row: two copies of
     * the same fact can disagree, and a line keyed to the wrong user's data key
     * is unrecoverable. Callers that write a fixed amount should set the `budget`
     * relation first — {@see App\Actions\Budgets\SaveBudget} does — so this
     * resolves without a query per line.
     */
    public function encryptionOwnerId(): ?int
    {
        $budget = $this->relationLoaded('budget')
            ? $this->budget
            : Budget::query()->find($this->attributes['budget_id'] ?? null);

        if (! $budget instanceof Budget) {
            return null;
        }

        return $budget->user_id === null ? null : (int) $budget->user_id;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rule_type' => BudgetRuleType::class,
            // Never encrypted: a share is not money, which is what lets the
            // server validate a plan whose amounts it cannot read.
            'percent' => 'decimal:2',
            'fixed_amount' => UserEncrypted::class.':decimal,2',
            // Nullable: only a fixed amount has a currency to be in. Read through
            // {@see self::currencyWithin()} rather than directly, so a line
            // without one falls back to its budget's.
            'currency' => Currency::class,
            'rollover_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
