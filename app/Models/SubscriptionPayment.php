<?php

namespace App\Models;

use App\Casts\UserEncrypted;
use App\Enums\BillingPlan;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentNetwork;
use App\Enums\PaymentStatus;
use App\Enums\SettlementAsset;
use App\Support\Billing\TokenAmount;
use Database\Factories\SubscriptionPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to buy a span of Pro access.
 *
 * The terms — plan, months, price, asset, decimals, contract, receiving address
 * and expected amount — are snapshotted when the intent opens and never
 * rewritten. Repricing a plan, rotating a wallet or a move in the market
 * therefore cannot change what an in-flight payment owes, and verification
 * always judges a transaction against the terms the buyer was actually shown.
 *
 * Deliberately not encrypted with {@see UserEncrypted}, unlike the user's own
 * financial records. These are the operator's business records: the amount is
 * our price, the address is ours, and the transaction hash is already public on
 * the chain. More to the point, the queue worker that settles a payment has no
 * browser and no per-user data key, so encrypting these would make a real
 * payment permanently unverifiable the moment its buyer armed their vault.
 */
#[Fillable([
    'user_id',
    'status',
    'plan',
    'months',
    'price_usd',
    'network',
    'chain_id',
    'asset',
    'token_contract',
    'asset_decimals',
    'pay_to_address',
    'quote_rate',
    'quote_expires_at',
    'expected_amount',
    'expires_at',
])]
class SubscriptionPayment extends Model
{
    /** @use HasFactory<SubscriptionPaymentFactory> */
    use HasFactory, HasUlids;

    /**
     * @return BelongsTo<User, SubscriptionPayment>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, SubscriptionPayment>
     */
    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_admin_id');
    }

    /** The amount this payment expects, in the asset's smallest on-chain unit. */
    public function expectedBaseUnits(): string
    {
        return TokenAmount::fromDecimal((string) $this->expected_amount, (int) $this->asset_decimals);
    }

    /** Whether the buyer can still pay against this intent. */
    public function isOpen(): bool
    {
        return $this->status === PaymentStatus::Pending && $this->expires_at->isFuture();
    }

    /**
     * Whether the rate this payment was priced at is still being honoured.
     *
     * Always true for a stablecoin, which carries no quote expiry at all.
     */
    public function quoteIsLive(): bool
    {
        return $this->quote_expires_at === null || $this->quote_expires_at->isFuture();
    }

    /**
     * Open intents, oldest first — the ones a buyer could still pay against.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', PaymentStatus::Pending->value)
            ->where('expires_at', '>', now());
    }

    /**
     * Payments that have claimed a transaction and are waiting on the chain.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function settling(Builder $query): void
    {
        $query->where('status', PaymentStatus::Submitted->value);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'plan' => BillingPlan::class,
            'months' => 'integer',
            'network' => PaymentNetwork::class,
            'chain_id' => 'integer',
            'asset' => SettlementAsset::class,
            'asset_decimals' => 'integer',
            'failure_reason' => PaymentFailureReason::class,
            'confirmations' => 'integer',
            'block_number' => 'integer',
            'attempts' => 'integer',
            'quote_expires_at' => 'datetime',
            'block_timestamp' => 'datetime',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
            // price_usd, quote_rate, expected_amount and received_amount are
            // deliberately uncast. They are stored in string columns and read
            // back as strings on every driver; a numeric cast would reintroduce
            // exactly the float that the column type exists to avoid.
        ];
    }
}
