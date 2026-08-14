<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreCouponRequest;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;

/**
 * Creating and retiring discount codes.
 *
 * Sits behind the same stack as the rest of the billing admin — the admin check
 * plus a fresh password confirmation — because a coupon is a way of giving away
 * paid access, and the admin check on its own is one string comparison.
 */
class AdminCouponController extends Controller
{
    public function store(StoreCouponRequest $request): RedirectResponse
    {
        Coupon::create($request->couponData());

        return back()->with('status', __('billing.admin.coupons.created'));
    }

    /**
     * Retire a code without erasing it.
     *
     * Preferred over deletion in every case where the coupon has been used: the
     * redemptions have to stay explicable, and they cite this row.
     */
    public function disable(Coupon $coupon): RedirectResponse
    {
        if (! $coupon->isDisabled()) {
            $coupon->forceFill(['disabled_at' => now()])->save();
        }

        return back()->with('status', __('billing.admin.coupons.disabled'));
    }

    public function enable(Coupon $coupon): RedirectResponse
    {
        $coupon->forceFill(['disabled_at' => null])->save();

        return back()->with('status', __('billing.admin.coupons.enabled'));
    }

    /**
     * Only a code nobody ever used may be deleted.
     *
     * Anything else is disabled instead — removing it would orphan the
     * redemption ledger and, through it, the reason somebody has Pro.
     */
    public function destroy(Coupon $coupon): RedirectResponse
    {
        abort_if($coupon->redemptions()->exists(), 409);

        $coupon->delete();

        return back()->with('status', __('billing.admin.coupons.deleted'));
    }
}
