<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Billing\RedeemFreeCoupon;
use App\Actions\Billing\ResolveCoupon;
use App\Actions\Billing\SettleCouponRedemption;
use App\Actions\Billing\StartSubscriptionPayment;
use App\Actions\Billing\SubmitPaymentProof;
use App\Contracts\Billing\AddressScreener;
use App\Enums\BillingPlan;
use App\Enums\CouponRedemptionStatus;
use App\Enums\DepositAddressStatus;
use App\Enums\PaymentNetwork;
use App\Enums\PaymentStatus;
use App\Enums\SettlementAsset;
use App\Exceptions\CouponUnavailable;
use App\Exceptions\DepositAddressLimitExceeded;
use App\Exceptions\DepositAddressUnavailable;
use App\Exceptions\QuoteUnavailable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StartPaymentRequest;
use App\Http\Requests\Settings\SubmitPaymentProofRequest;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\Billing\BillingCatalog;
use App\Support\Billing\ScreeningSubject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(
        private readonly StartSubscriptionPayment $startPayment,
        private readonly SubmitPaymentProof $submitProof,
        private readonly BillingCatalog $catalog,
        private readonly ResolveCoupon $resolveCoupon,
        private readonly RedeemFreeCoupon $redeemFreeCoupon,
        private readonly SettleCouponRedemption $settleCoupon,
        private readonly AddressScreener $addressScreener,
    ) {}

    public function edit(Request $request): Response
    {
        $this->assertBillingIsAvailable();

        $user = $request->user();

        return Inertia::render('settings/Billing', [
            'plans' => $this->catalog->plans(),
            'networks' => $this->catalog->networks(),
            'pending' => $this->pendingFor($request),
            'preferred' => $this->preferredRail($request),
            'history' => $this->historyFor($user),
            'status' => $request->session()->get('status'),
            // Set only on the redirect that follows redeeming a full-price
            // coupon. Drives the confirmation the buyer gets instead of a
            // payment screen; absent on every other load.
            'activated' => $request->session()->get('activated'),
        ]);
    }

    public function store(StartPaymentRequest $request): RedirectResponse
    {
        $this->assertBillingIsAvailable();

        $user = $request->user();
        $plan = $request->plan();
        $code = $request->couponCode();
        $coupon = null;

        if ($code !== null) {
            $resolution = ($this->resolveCoupon)($user, $code, $plan);

            if (! $resolution->accepted) {
                return back()->withErrors(['coupon' => $resolution->rejection->label()]);
            }

            // A coupon covering the whole price has nothing to settle on-chain —
            // a zero transfer is not something a chain can carry — so it grants
            // the months outright instead of opening an intent nobody could pay.
            if ($resolution->coversEverything) {
                $rejection = ($this->redeemFreeCoupon)($user, $resolution->coupon, $plan);

                if ($rejection !== null) {
                    return back()->withErrors(['coupon' => $rejection->label()]);
                }

                // Its own flash rather than another `status` string. The page has
                // to tell "your months are on the account, nothing was charged"
                // apart from every other green strip it shows, and matching on
                // the translated sentence would break the moment it is reworded
                // or read in another locale.
                return back()->with('activated', [
                    'plan_label' => $plan->label(),
                    'months' => $plan->months(),
                    'coupon_code' => $resolution->coupon->code,
                ]);
            }

            $coupon = $resolution->coupon;
        }

        try {
            ($this->startPayment)($user, $plan, $request->network(), $request->asset(), $coupon);
        } catch (QuoteUnavailable) {
            // Never fall through to a default rate. Quoting a plan at a stale or
            // zero price is worse than telling the buyer to come back.
            return back()->withErrors(['plan' => __('billing.errors.quote_unavailable')]);
        } catch (CouponUnavailable $exception) {
            // Somebody took the last use between resolving and claiming.
            return back()->withErrors(['coupon' => $exception->rejection->label()]);
        } catch (DepositAddressUnavailable) {
            return back()->withErrors(['network' => __('billing.errors.deposit_pool_empty')]);
        } catch (DepositAddressLimitExceeded) {
            return back()->withErrors(['plan' => __('billing.errors.deposit_address_limit')]);
        }

        return back()->with('status', __('billing.pay.created'));
    }

    /**
     * Check a code and price every plan against it, without claiming anything.
     *
     * Exists so the buyer can see what a code is worth before committing to a
     * plan. ResolveCoupon takes no use and has no side effects, so this is safe
     * to call as somebody types; the authoritative check still happens under a
     * lock when the intent is actually opened.
     */
    public function previewCoupon(Request $request): JsonResponse
    {
        $this->assertBillingIsAvailable();

        $code = Coupon::normalizeCode((string) $request->input('coupon'));

        if ($code === '') {
            return response()->json([
                'accepted' => false,
                'message' => __('billing.coupon.rejections.not_found'),
            ]);
        }

        $plans = [];

        foreach (BillingPlan::available() as $plan) {
            $resolution = ($this->resolveCoupon)($request->user(), $code, $plan);

            // A code is valid or not on its own terms, so the first refusal is
            // the answer for all of them.
            if (! $resolution->accepted) {
                return response()->json([
                    'accepted' => false,
                    'message' => $resolution->rejection->label(),
                ]);
            }

            $plans[$plan->value] = [
                'list_price_usd' => $resolution->listPriceUsd,
                'discount_usd' => $resolution->discountUsd,
                'final_price_usd' => $resolution->finalPriceUsd,
                'covers_everything' => $resolution->coversEverything,
            ];
        }

        return response()->json([
            'accepted' => true,
            'code' => $code,
            'plans' => $plans,
        ]);
    }

    public function precheckWallet(Request $request): JsonResponse
    {
        $this->assertBillingIsAvailable();
        $validated = $request->validate([
            'network' => ['required', 'in:ethereum,arbitrum'],
            'address' => ['required', 'regex:/^0x[0-9a-fA-F]{40}$/'],
        ]);
        $network = PaymentNetwork::from($validated['network']);
        $address = $network->normalizeAddress($validated['address']);
        $result = $this->addressScreener->screen(new ScreeningSubject(
            network: $network,
            transactionHash: str_repeat('0', 66),
            senderAddress: $address,
            recipientAddress: $address,
            asset: SettlementAsset::Eth,
            receivedAmount: '0',
        ));

        return response()->json([
            'risk' => $result->risk->value,
            'advisory' => true,
            'message' => __('billing.precheck.results.'.$result->risk->value),
        ]);
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
        // Cast both sides, as every other ownership check here does: a driver that
        // returns integer columns as strings would otherwise fail this comparison
        // for the owner. It fails closed, so it was never a way in — but the tests
        // run SQLite and production runs MySQL, so it is not a difference the
        // suite would ever show.
        abort_unless((int) $payment->user_id === (int) $request->user()->getKey(), 404);

        // Only an unpaid intent can be withdrawn. Anything that has claimed a
        // transaction is evidence now, and stays.
        abort_unless($payment->status === PaymentStatus::Pending, 404);

        // Withdrawn, not expired. Both are terminal and both hand the coupon
        // back, but the history is the only place either is ever read, and
        // "Expired" against an intent the buyer cancelled on purpose reads as
        // something that failed on them.
        DB::transaction(function () use ($payment): void {
            $payment->forceFill(['status' => PaymentStatus::Cancelled])->save();
            $payment->depositAddress?->forceFill(['status' => DepositAddressStatus::Retired])->save();
        });

        // Withdrawing an intent hands back any coupon it was holding, so a
        // single-use code is not spent by somebody who changed their mind.
        $this->settleCoupon->release($payment);

        return back()->with('status', __('billing.pay.cancelled'));
    }

    /**
     * The chain and asset this buyer reached for last time.
     *
     * Read from their own payment history rather than stored as a preference:
     * choosing a rail and opening an intent already records the choice, so
     * there is nothing to keep in sync, and it follows them to another device
     * the way a browser-local setting would not.
     *
     * The page falls back on its own if either is no longer on offer, so a
     * network switched off since does not leave somebody stuck on it.
     *
     * @return array<string, string>|null
     */
    private function preferredRail(Request $request): ?array
    {
        $last = $request->user()->subscriptionPayments()
            ->whereNotNull('network')
            ->latest('created_at')
            ->first();

        return $last === null ? null : [
            'network' => $last->network->value,
            'asset' => $last->asset->value,
        ];
    }

    /**
     * Everything that has moved this buyer's subscription, newest first.
     *
     * Payments alone were not the whole story. A coupon covering the full price
     * opens no intent — a chain cannot carry a zero transfer — so redeeming one
     * granted the months and left this list either empty or, worse, showing only
     * the intent the buyer had abandoned in order to go and use the code. Read
     * from both sources and merged, so the list answers "what happened to my
     * subscription" rather than "which chain transfers did I start".
     *
     * @return list<array<string, mixed>>
     */
    private function historyFor(User $user): array
    {
        $payments = $user->subscriptionPayments()
            // The presenter reads the coupon's code, so eager load it rather
            // than issuing a query per row.
            ->with(['coupon:id,code', 'riskCase'])
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn (SubscriptionPayment $payment): array => $this->catalog->presentPaymentHistoryEntry($payment));

        $couponGrants = $user->couponRedemptions()
            // Only the ones that stood in for a payment. A claim attached to a
            // payment is already represented by that payment's own row, and
            // listing it twice would read as two purchases.
            ->whereNull('subscription_payment_id')
            ->whereNotNull('subscription_grant_id')
            ->where('status', CouponRedemptionStatus::Consumed)
            ->with(['coupon:id,code', 'grant'])
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn (CouponRedemption $redemption): array => $this->catalog->presentCouponHistoryEntry($redemption));

        return $payments
            ->concat($couponGrants)
            ->sortByDesc('created_at')
            ->take(20)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pendingFor(Request $request): ?array
    {
        $pending = $request->user()->subscriptionPayments()
            ->with('coupon:id,code')
            ->inFlight()
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
