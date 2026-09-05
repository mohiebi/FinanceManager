<?php

namespace App\Http\Controllers;

use App\Support\AcquisitionSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The front door of a shared invite link.
 *
 * A path segment survives being pasted around; a query string is the first
 * thing chat apps drop. The code is remembered, never trusted - an unknown one
 * just lands on the marketing page.
 */
class InviteController extends Controller
{
    public function __invoke(Request $request, string $code): RedirectResponse
    {
        AcquisitionSource::rememberReferral($request, $code);

        return redirect()->route('home');
    }
}
