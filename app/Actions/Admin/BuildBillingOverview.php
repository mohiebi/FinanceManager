<?php

namespace App\Actions\Admin;

use App\Enums\DepositAddressStatus;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentNetwork;
use App\Enums\PaymentStatus;
use App\Enums\ScreeningRisk;
use App\Models\DepositAddress;
use App\Models\PaymentRiskCase;
use App\Models\PaymentSettlement;
use App\Models\RiskAddress;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\Billing\WalletSignerClient;
use App\Support\Billing\BillingCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The operator's view of subscriptions.
 *
 * Built around one question — which payments need a person — rather than around
 * a table of everything. Automatic verification refuses to guess whenever money
 * probably arrived but the rules cannot honour it, and this is where those land.
 */
final readonly class BuildBillingOverview
{
    /**
     * How long a payment may sit unverified before it is worth a look.
     */
    private const STALLED_AFTER_MINUTES = 60;

    public function __construct(private BillingCatalog $catalog, private WalletSignerClient $signer) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'counts' => $this->counts(),
            'poolHealth' => $this->poolHealth(),
            'signerHealth' => $this->signerHealth(),
            'riskCases' => $this->riskCases(),
            'settlements' => $this->settlements(),
            'riskEntries' => $this->riskEntries(),
            'needsAttention' => $this->present($this->needsAttention()),
            'delayedScreening' => $this->present($this->delayedScreening()),
            'quarantined' => $this->presentDeposits($this->deposits(DepositAddressStatus::Quarantined)),
            'completedSweeps' => $this->presentDeposits($this->deposits(DepositAddressStatus::Swept)),
            'recent' => $this->present(
                SubscriptionPayment::query()
                    ->with(['user:id,name,email', 'coupon:id,code'])
                    ->latest('created_at')
                    ->limit(50)
                    ->get()
            ),
            'proUsers' => $this->proUsers(),
        ];
    }

    /**
     * The signer's own account of itself.
     *
     * Cached because this page is refreshed freely and the call behind it makes
     * RPC requests of its own. The failure branch reports that the signer could
     * not be reached and nothing else: the exception carries internal hostnames
     * and secret paths, and the page it would render on is a browser tab like
     * any other.
     *
     * @return array<string, mixed>
     */
    private function signerHealth(): array
    {
        return Cache::remember('billing.signer-health', now()->addSeconds(30), function (): array {
            try {
                return $this->signer->health();
            } catch (\Throwable $exception) {
                report($exception);

                return ['ok' => false, 'locked' => true, 'unreachable' => true];
            }
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function riskCases(): array
    {
        return PaymentRiskCase::query()->with(['payment.user', 'payment.settlement'])
            ->latest()->limit(50)->get()->map(fn (PaymentRiskCase $case): array => [
                'id' => $case->getKey(),
                'payment_id' => $case->subscription_payment_id,
                'user_email' => $case->payment->user->email,
                'source_address' => $case->source_address,
                'network' => $case->network->value,
                'status' => $case->status->value,
                'review_expires_at' => $case->review_expires_at->toIso8601String(),
                'settlement_status' => $case->payment->settlement?->status->value,
                'authorization_note' => $case->authorization_note,
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function settlements(): array
    {
        return PaymentSettlement::query()->with('payment.user')->latest()->limit(50)->get()
            ->map(fn (PaymentSettlement $settlement): array => [
                'id' => $settlement->getKey(),
                'operation_id' => $settlement->operation_id,
                'payment_id' => $settlement->subscription_payment_id,
                'user_email' => $settlement->payment->user->email,
                'network' => $settlement->payment->network->value,
                'asset' => $settlement->payment->asset->value,
                'status' => $settlement->status->value,
                'transaction_hashes' => $settlement->transaction_hashes ?? [],
                'remaining_token_balance' => $settlement->remaining_token_balance,
                'remaining_eth_wei' => $settlement->remaining_eth_wei,
                'failure_code' => $settlement->failure_code,
                'failure_message' => $settlement->failure_message,
                'updated_at' => $settlement->updated_at->toIso8601String(),
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function riskEntries(): array
    {
        return RiskAddress::query()->with('createdByAdmin:id,email')->latest()->limit(100)->get()
            ->map(fn (RiskAddress $entry): array => [
                'id' => $entry->getKey(),
                'network' => $entry->network->value,
                'address' => $entry->address,
                'source' => $entry->source,
                'reason' => $entry->reason,
                'active' => $entry->active,
                'admin_email' => $entry->createdByAdmin->email,
                'created_at' => $entry->created_at->toIso8601String(),
            ])->all();
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $byStatus = SubscriptionPayment::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [];

        foreach (PaymentStatus::cases() as $status) {
            $counts[$status->value] = (int) ($byStatus[$status->value] ?? 0);
        }

        $counts['pro_users'] = User::query()->where('pro_until', '>', now())->count();

        return $counts;
    }

    /**
     * Payments a person has to decide on.
     *
     * Two kinds: those whose verdict explicitly asks for review, and those that
     * simply stopped moving — which usually means the chain could not be read.
     *
     * @return Collection<int, SubscriptionPayment>
     */
    private function needsAttention(): Collection
    {
        $reviewable = array_column(PaymentFailureReason::needingReview(), 'value');

        return SubscriptionPayment::query()
            ->with(['user:id,name,email', 'coupon:id,code'])
            ->where(fn ($query) => $query
                ->whereIn('failure_reason', $reviewable)
                ->orWhere(fn ($stalled) => $stalled
                    ->where('status', PaymentStatus::Submitted->value)
                    ->whereNull('chain_verified_at')
                    ->where('updated_at', '<=', now()->subMinutes(self::STALLED_AFTER_MINUTES))))
            ->whereNotIn('status', [PaymentStatus::Confirmed->value, PaymentStatus::Quarantined->value])
            ->latest('updated_at')
            ->limit(50)
            ->get();
    }

    /** @return Collection<int, SubscriptionPayment> */
    private function delayedScreening(): Collection
    {
        $delayedBefore = now()->subMinutes(self::STALLED_AFTER_MINUTES);

        return SubscriptionPayment::query()
            ->with(['user:id,name,email', 'coupon:id,code'])
            ->where('status', PaymentStatus::Submitted->value)
            ->whereNotNull('chain_verified_at')
            ->where(fn ($query) => $query
                ->where(fn ($neverScreened) => $neverScreened
                    ->whereNull('screening_risk')
                    ->where('chain_verified_at', '<=', $delayedBefore))
                ->orWhere(fn ($unknown) => $unknown
                    ->where('screening_risk', ScreeningRisk::Unknown->value)
                    ->where('screened_at', '<=', $delayedBefore)))
            ->latest('updated_at')
            ->limit(50)
            ->get();
    }

    /** @return array<int, array<string, mixed>> */
    private function poolHealth(): array
    {
        $warningAt = (int) config('billing.deposit_pool.low_address_warning', 25);

        return collect(PaymentNetwork::cases())->map(function (PaymentNetwork $network) use ($warningAt): array {
            $counts = DepositAddress::query()
                ->where(fn ($query) => $query->whereNull('network')->orWhere('network', $network->value))
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $available = (int) ($counts[DepositAddressStatus::Available->value] ?? 0);

            return [
                'network' => $network->value,
                'network_label' => $network->label(),
                'available' => $available,
                'assigned' => (int) ($counts[DepositAddressStatus::Assigned->value] ?? 0),
                'retired' => (int) ($counts[DepositAddressStatus::Retired->value] ?? 0),
                'quarantined' => (int) ($counts[DepositAddressStatus::Quarantined->value] ?? 0),
                'warning' => $available <= $warningAt,
                'checkout_available' => $network->isEnabled() && $available > 0,
            ];
        })->all();
    }

    /** @return Collection<int, DepositAddress> */
    private function deposits(DepositAddressStatus $status): Collection
    {
        return DepositAddress::query()
            ->with(['payment.user:id,name,email'])
            ->where('status', $status->value)
            ->latest('updated_at')
            ->limit(50)
            ->get();
    }

    /**
     * @param  Collection<int, DepositAddress>  $deposits
     * @return array<int, array<string, mixed>>
     */
    private function presentDeposits(Collection $deposits): array
    {
        return $deposits->map(function (DepositAddress $deposit): array {
            $payment = $deposit->payment;

            return [
                'id' => $deposit->getKey(),
                'network' => $deposit->network?->value,
                'network_label' => $deposit->network?->label(),
                'address' => $deposit->address,
                'derivation_index' => $deposit->derivation_index,
                'status' => $deposit->status->value,
                'payment_id' => $payment?->getKey(),
                'user_email' => $payment?->user?->email,
                'asset' => $payment?->asset->value,
                'asset_symbol' => $payment?->asset->symbol(),
                'amount' => $payment?->received_amount,
                'screening_risk' => $payment?->screening_risk?->value,
                'block_timestamp' => $payment?->block_timestamp?->toIso8601String(),
                'quarantine_reason' => $deposit->quarantine_reason,
                'quarantined_at' => $deposit->quarantined_at?->toIso8601String(),
                'swept_at' => $deposit->swept_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, SubscriptionPayment>  $payments
     * @return array<int, array<string, mixed>>
     */
    private function present(Collection $payments): array
    {
        // Sending addresses reused across accounts are the cheapest signal of
        // coordinated abuse available here, so they are counted once and flagged
        // rather than left for somebody to notice by eye.
        $shared = SubscriptionPayment::query()
            ->whereNotNull('from_address')
            ->selectRaw('from_address, count(distinct user_id) as owners')
            ->groupBy('from_address')
            ->having('owners', '>', 1)
            ->pluck('owners', 'from_address');

        return $payments->map(fn (SubscriptionPayment $payment): array => [
            ...$this->catalog->presentPayment($payment),
            'user' => [
                'id' => $payment->user?->id,
                'name' => $payment->user?->name,
                'email' => $payment->user?->email,
            ],
            'from_address' => $payment->from_address,
            'from_address_shared_with' => $payment->from_address === null
                ? 0
                : (int) ($shared[$payment->from_address] ?? 0),
            'admin_note' => $payment->admin_note,
            'attempts' => $payment->attempts,
            'updated_at' => $payment->updated_at->toIso8601String(),
        ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function proUsers(): array
    {
        return User::query()
            ->where('pro_until', '>', now())
            ->with(['subscriptionGrants' => fn ($query) => $query->latest('created_at')->limit(1)])
            ->orderBy('pro_until')
            ->limit(100)
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'pro_until' => $user->pro_until->toIso8601String(),
                'last_reason' => $user->subscriptionGrants->first()?->reason->label(),
            ])
            ->all();
    }
}
