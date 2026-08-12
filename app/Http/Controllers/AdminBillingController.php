<?php

namespace App\Http\Controllers;

use App\Actions\Admin\BuildBillingOverview;
use App\Actions\Billing\GrantProAccess;
use App\Actions\Billing\RevokeProAccess;
use App\Enums\GrantReason;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentStatus;
use App\Jobs\VerifySubscriptionPaymentJob;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    ) {}

    public function index(BuildBillingOverview $overview): Response
    {
        return Inertia::render('admin/Billing', [
            ...$overview(),
            'status' => request()->session()->get('status'),
        ]);
    }

    /**
     * Honour a payment the automatic checks would not.
     *
     * The escape hatch for every case the verifier deliberately refuses to
     * guess at: an unreadable internal transfer, an amount an exchange shaved a
     * fee off, a quote that lapsed while the transaction was in flight, or a
     * chain we simply could not reach.
     */
    public function approve(Request $request, SubscriptionPayment $payment): RedirectResponse
    {
        $note = $this->requireNote($request);

        abort_if($payment->status === PaymentStatus::Confirmed, 409);

        $payment->forceFill([
            'status' => PaymentStatus::Confirmed,
            'approved_by_admin_id' => $request->user()->getKey(),
            'admin_note' => $note,
            'verified_at' => now(),
            'failure_reason' => null,
        ])->save();

        ($this->grantProAccess)(
            user: $payment->user,
            months: (int) $payment->months,
            reason: GrantReason::AdminApprovePayment,
            paymentId: $payment->getKey(),
            admin: $request->user(),
            note: $note,
        );

        return back()->with('status', __('billing.admin.approved'));
    }

    public function reject(Request $request, SubscriptionPayment $payment): RedirectResponse
    {
        $note = $this->requireNote($request);

        abort_if($payment->status === PaymentStatus::Confirmed, 409);

        $payment->forceFill([
            'status' => PaymentStatus::Failed,
            'failure_reason' => PaymentFailureReason::AdminRejected,
            'approved_by_admin_id' => $request->user()->getKey(),
            'admin_note' => $note,
        ])->save();

        return back()->with('status', __('billing.admin.rejected'));
    }

    /**
     * Ask the chain again — for a payment stranded by an outage that has since
     * passed.
     */
    public function recheck(SubscriptionPayment $payment): RedirectResponse
    {
        abort_unless($payment->status === PaymentStatus::Submitted, 409);

        VerifySubscriptionPaymentJob::dispatch($payment->getKey());

        return back()->with('status', __('billing.admin.rechecking'));
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
