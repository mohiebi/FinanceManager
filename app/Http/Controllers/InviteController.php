<?php

namespace App\Http\Controllers;

use App\Support\AcquisitionSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The front door of a shared invite link.
 *
 * A referral could ride on `?ref=` alone, and does elsewhere, but a query
 * string is the first thing chat apps and link shorteners drop - and a link
 * whose credit silently vanishes in transit is worse than no link. A path
 * segment survives being pasted around, which is the only thing an invite is
 * ever asked to do.
 *
 * The code is only remembered, never trusted: it buys a session note that a
 * real wallet must claim later, and an unknown or expired one simply lands the
 * visitor on the marketing page like any other arrival.
 */
class InviteController extends Controller
{
    public function __invoke(Request $request, string $code): RedirectResponse
    {
        AcquisitionSource::rememberReferral($request, $code);

        return redirect()->route('home');
    }
}
