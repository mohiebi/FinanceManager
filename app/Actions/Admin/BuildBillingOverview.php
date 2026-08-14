<?php

namespace App\Actions\Admin;

use App\Enums\PaymentFailureReason;
use App\Enums\PaymentStatus;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\Billing\BillingCatalog;
use Illuminate\Support\Collection;

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

    public function __construct(private BillingCatalog $catalog) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'counts' => $this->counts(),
            'needsAttention' => $this->present($this->needsAttention()),
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
                    ->where('updated_at', '<=', now()->subMinutes(self::STALLED_AFTER_MINUTES))))
            ->whereNot('status', PaymentStatus::Confirmed->value)
            ->latest('updated_at')
            ->limit(50)
            ->get();
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
