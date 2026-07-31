<?php

namespace App\Http\Controllers;

use App\Actions\Gamification\RecordNoSpendDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NoSpendDayController extends Controller
{
    /**
     * Mark today as spend-free.
     *
     * Only ever today: back-marking an arbitrary past day would let a user
     * manufacture a streak, and the honest way to fill a gap in the record is
     * to enter what was actually spent.
     */
    public function store(Request $request, RecordNoSpendDay $recordNoSpendDay): RedirectResponse
    {
        $marked = $recordNoSpendDay->handle($request->user());

        return back()->with(
            'status',
            __($marked === null ? 'gamification.no_spend_conflict' : 'gamification.no_spend_recorded'),
        );
    }
}
