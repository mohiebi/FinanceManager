<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Confines route model binding to rows the signed-in user owns.
 *
 * Every controller that took one of these models used to re-derive the same
 * check by hand — `abort_unless((int) $model->user_id === (int) $request->user()->id, 404)`
 * — eleven times over, which made the guarantee only as reliable as the next
 * person's memory. It has already failed once: the investment update route was
 * ownership-checked but not disposal-checked, and nothing about writing that
 * route made the omission visible.
 *
 * Binding is the right place because it is not optional. A route that names one
 * of these models cannot opt out of the scope, cannot forget it, and cannot get
 * the comparison subtly wrong — so a new route is safe before anybody reviews it.
 *
 * A miss resolves to no model at all, which Laravel turns into the same 404 the
 * hand-written checks deliberately returned: a record belonging to someone else
 * should not be confirmed to exist, which is the one thing a 403 would leak.
 *
 * Deliberately not applied to:
 * - `Category` and `InvestmentAsset`, whose defaults are shared rows with a null
 *   `user_id`; they scope through `availableFor()` instead.
 * - `SubscriptionPayment`, bound by both the owner's settings routes and the
 *   admin console, where the actor is deliberately not the owner.
 * - `BillOccurrence`, which reaches its owner through `bill_id` rather than
 *   carrying a `user_id` of its own.
 */
trait ScopedToOwner
{
    /**
     * Hooks `resolveRouteBinding` rather than `resolveRouteBindingQuery`.
     *
     * The query builder is where the owner constraint naturally belongs, but
     * `HasUlids` already defines that method, and two traits declaring the same
     * method is a fatal collision rather than an override. Composing one level
     * up keeps whatever a model's own traits do to the lookup — the ULID format
     * check included — and simply refuses to return anything the signed-in user
     * does not own.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $ownerId = Auth::id();

        // These routes all sit behind `auth`, so this is a belt rather than a
        // brace — but a binding that resolved for a guest would be the one bug
        // worth never risking.
        if ($ownerId === null) {
            return null;
        }

        return $this->resolveRouteBindingQuery($this, $value, $field)
            ->where($this->qualifyColumn('user_id'), $ownerId)
            ->first();
    }
}
