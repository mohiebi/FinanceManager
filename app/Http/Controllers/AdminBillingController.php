<?php

namespace App\Http\Controllers;

use App\Actions\Admin\BuildBillingOverview;
use App\Actions\Admin\BuildCouponOverview;
use App\Actions\Billing\AuthorizeDepositSweep;
use App\Actions\Billing\GrantProAccess;
use App\Actions\Billing\RecordDepositSweep;
use App\Actions\Billing\RevokeProAccess;
use App\Actions\Billing\SettleCouponRedemption;
use App\Enums\DepositAddressStatus;
use App\Enums\GrantReason;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentStatus;
use App\Exceptions\SweepAuthorizationDenied;
use App\Exceptions\SweepRecordRejected;
use App\Jobs\ScreenSubscriptionPaymentJob;
use App\Jobs\VerifySubscriptionPaymentJob;
use App\Models\DepositAddress;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The manual half of billing.
 *
 * Deliberately small, and every mutating route sits behind a fresh password
 * confirmation on top of the admin check — that check is a single string
 * comparison against a configured email, which is thin protection for the one
 * surface in the app that hands out entitlements for free.
 *
 * Every action here requires a written note. An audit trail nobody explained is
 * only marginally better than none.
 */
class AdminBillingController extends Controller
{
    public function __construct(
        private readonly GrantProAccess $grantProAccess,
        private readonly RevokeProAccess $revokeProAccess,
        private readonly SettleCouponRedemption $settleCouponRedemption,
    ) {}

    public function index(BuildBillingOverview $overview, BuildCouponOverview $coupons): Response
    {
        return Inertia::render('admin/Billing', [
            ...$overview(),
            'coupons' => $coupons(),
            'status' => request()->session()->get('status'),
        ]);
    }

    /**
     * Accept a chain anomaly for sanctions screening.
     *
     * The escape hatch for every case the verifier deliberately refuses to
     * guess at: an unreadable internal transfer, an amount an exchange shaved a
     * fee off, a quote that lapsed while the transaction was in flight, or a
     * chain we simply could not reach. This never grants entitlement itself:
     * only a later NoMatch result may do that.
     */
    public function approve(Request $request, SubscriptionPayment $payment): RedirectResponse
    {
        $note = $this->requireNote($request);

        abort_if(in_array($payment->status, [PaymentStatus::Confirmed, PaymentStatus::Quarantined], true), 409);
        abort_unless($payment->failure_reason?->needsReview() === true, 409);
        abort_if(blank($payment->tx_hash) || blank($payment->from_address), 409);

        DB::transaction(function () use ($payment, $request, $note): void {
            $locked = SubscriptionPayment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            abort_if(in_array($locked->status, [PaymentStatus::Confirmed, PaymentStatus::Quarantined], true), 409);
            abort_unless($locked->failure_reason?->needsReview() === true, 409);

            $locked->forceFill([
                'status' => PaymentStatus::Submitted,
                'approved_by_admin_id' => $request->user()->getKey(),
                'admin_note' => $note,
                'chain_verified_at' => now(),
                'failure_reason' => null,
            ])->save();

            ScreenSubscriptionPaymentJob::dispatch($locked->getKey())->afterCommit();
        });

        return back()->with('status', __('billing.admin.approved'));
    }

    public function reject(Request $request, SubscriptionPayment $payment): RedirectResponse
    {
        $note = $this->requireNote($request);

        abort_if(
            in_array($payment->status, [PaymentStatus::Confirmed, PaymentStatus::Quarantined], true)
            || $payment->chain_verified_at !== null,
            409,
        );

        $payment->forceFill([
            'status' => PaymentStatus::Failed,
            'failure_reason' => PaymentFailureReason::AdminRejected,
            'approved_by_admin_id' => $request->user()->getKey(),
            'admin_note' => $note,
        ])->save();
        $payment->depositAddress?->forceFill(['status' => DepositAddressStatus::Retired])->save();

        // Nothing was paid for, so the coupon claim goes back into the pool.
        $this->settleCouponRedemption->release($payment);

        return back()->with('status', __('billing.admin.rejected'));
    }

    /**
     * Ask the chain again — for a payment stranded by an outage that has since
     * passed.
     */
    public function recheck(SubscriptionPayment $payment): RedirectResponse
    {
        abort_unless($payment->status === PaymentStatus::Submitted, 409);

        if ($payment->chain_verified_at !== null) {
            ScreenSubscriptionPaymentJob::dispatch($payment->getKey());
        } else {
            VerifySubscriptionPaymentJob::dispatch($payment->getKey());
        }

        return back()->with('status', __('billing.admin.rechecking'));
    }

    public function authorizeSweep(
        Request $request,
        DepositAddress $depositAddress,
        AuthorizeDepositSweep $authorizeDepositSweep,
    ): RedirectResponse {
        try {
            $authorizeDepositSweep($depositAddress, $request->user());
        } catch (SweepAuthorizationDenied $exception) {
            return back()->withErrors(['sweep' => __("billing.admin.sweep_errors.{$exception->reason}")]);
        }

        return back()->with('status', __('billing.admin.sweep_authorized'));
    }

    public function recordSweep(
        Request $request,
        DepositAddress $depositAddress,
        RecordDepositSweep $recordDepositSweep,
    ): RedirectResponse {
        $validated = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:2000'],
            'sweep_tx_hash' => ['required', 'string', 'max:80'],
            'conversion_tx_hash' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $recordDepositSweep(
                depositAddress: $depositAddress,
                admin: $request->user(),
                note: $validated['note'],
                sweepTransactionHash: $validated['sweep_tx_hash'],
                conversionTransactionHash: $validated['conversion_tx_hash'] ?? null,
            );
        } catch (SweepRecordRejected $exception) {
            return back()->withErrors(['sweep' => __("billing.admin.sweep_errors.{$exception->reason}")]);
        }

        return back()->with('status', __('billing.admin.sweep_recorded'));
    }

    public function grant(Request $request, User $user): RedirectResponse
    {
        $note = $this->requireNote($request);

        $months = (int) $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:120'],
        ])['months'];

        ($this->grantProAccess)(
            user: $user,
            months: $months,
            reason: GrantReason::AdminGrant,
            admin: $request->user(),
            note: $note,
        );

        return back()->with('status', __('billing.admin.granted'));
    }

    public function revoke(Request $request, User $user): RedirectResponse
    {
        ($this->revokeProAccess)($user, $request->user(), $this->requireNote($request));

        return back()->with('status', __('billing.admin.revoked'));
    }

    private function requireNote(Request $request): string
    {
        return (string) $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:500'],
        ])['note'];
    }
}
