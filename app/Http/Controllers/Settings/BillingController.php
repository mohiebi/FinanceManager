<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Billing\StartSubscriptionPayment;
use App\Actions\Billing\SubmitPaymentProof;
use App\Enums\PaymentStatus;
use App\Exceptions\QuoteUnavailable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StartPaymentRequest;
use App\Http\Requests\Settings\SubmitPaymentProofRequest;
use App\Models\SubscriptionPayment;
use App\Support\Billing\BillingCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(
        private readonly StartSubscriptionPayment $startPayment,
        private readonly SubmitPaymentProof $submitProof,
        private readonly BillingCatalog $catalog,
    ) {}

    public function edit(Request $request): Response
    {
        $this->assertBillingIsAvailable();

        $user = $request->user();

        return Inertia::render('settings/Billing', [
            'plans' => $this->catalog->plans(),
            'networks' => $this->catalog->networks(),
            'pending' => $this->pendingFor($request),
            'payments' => $user->subscriptionPayments()
                ->latest('created_at')
                ->limit(20)
                ->get()
                ->map(fn (SubscriptionPayment $payment): array => $this->catalog->presentPayment($payment))
                ->values(),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(StartPaymentRequest $request): RedirectResponse
    {
        $this->assertBillingIsAvailable();

        try {
            ($this->startPayment)(
                $request->user(),
                $request->plan(),
                $request->network(),
                $request->asset(),
            );
        } catch (QuoteUnavailable) {
            // Never fall through to a default rate. Quoting a plan at a stale or
            // zero price is worse than telling the buyer to come back.
            return back()->withErrors(['plan' => __('billing.errors.quote_unavailable')]);
        }

        return back()->with('status', __('billing.pay.created'));
    }

    public function submitProof(SubmitPaymentProofRequest $request, SubscriptionPayment $payment): RedirectResponse
    {
        $this->assertBillingIsAvailable();

        $result = ($this->submitProof)($payment, $request->validated('tx_hash'));

        if (! $result->accepted) {
            return back()->withErrors(['tx_hash' => $result->reason?->label() ?? __('billing.errors.generic')]);
        }

        return back()->with('status', __('billing.pay.submitted'));
    }

    public function destroy(Request $request, SubscriptionPayment $payment): RedirectResponse
    {
        $this->assertBillingIsAvailable();

        // 404 rather than 403 on somebody else's payment: a ULID is not
        // guessable, and confirming one exists is the only thing a 403 adds.
        abort_unless($payment->user_id === $request->user()->getKey(), 404);

        // Only an unpaid intent can be withdrawn. Anything that has claimed a
        // transaction is evidence now, and stays.
        abort_unless($payment->status === PaymentStatus::Pending, 404);

        $payment->forceFill(['status' => PaymentStatus::Expired])->save();

        return back()->with('status', __('billing.pay.cancelled'));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pendingFor(Request $request): ?array
    {
        $pending = $request->user()->subscriptionPayments()
            ->open()
            ->latest('created_at')
            ->first();

        return $pending === null ? null : $this->catalog->presentPayment($pending);
    }

    /**
     * The kill switch, applied in the controller rather than by registering the
     * routes conditionally — conditional registration breaks route caching and
     * leaves Wayfinder with no helpers to generate.
     */
    private function assertBillingIsAvailable(): void
    {
        abort_unless($this->catalog->isAvailable(), 404);
    }
}
